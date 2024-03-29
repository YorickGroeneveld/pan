<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class UserController.
 */
class UserController extends AbstractController
{
    /**
     * @Route("/login")
     * @Template
     */
    public function login(
        Session $session,
        Request $request,
        AuthorizationCheckerInterface $authChecker,
        CommonGroundService $commonGroundService,
        ParameterBagInterface $params,
        EventDispatcherInterface $dispatcher
    ) {
        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => getenv('APP_ID')]);

        // Dealing with backUrls
        if ($backUrl = $request->query->get('backUrl')) {
        } else {
            $backUrl = '/login';
        }
        $session->set('backUrl', $backUrl);

        if ($this->getUser()) {
            if (isset($application['defaultConfiguration']['configuration']['userPage'])) {
                return $this->redirect($application['defaultConfiguration']['configuration']['userPage']);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        } else {
            return $this->render('login/index.html.twig', ['backUrl'=>$backUrl]);
        }
    }

    /**
     * @Route("/digispoof")
     * @Template
     */
    public function DigispoofAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $redirect = $commonGroundService->cleanUrl(['component' => 'ds']);

        return $this->redirect($redirect.'?responceUrl='.$request->query->get('response').'&backUrl='.$request->query->get('back_url'));
    }

    /**
     * @Route("/eherkenning")
     * @Template
     */
    public function EherkenningAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $redirect = $commonGroundService->cleanUrl(['component' => 'eh']);

        return $this->redirect($redirect.'?responceUrl='.$request->query->get('response').'&backUrl='.$request->query->get('back_url'));
    }

    /**
     * @Route("/idin")
     * @Template
     */
    public function IdinAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        return $this->redirect('https://eu01.preprod.signicat.com/oidc/authorize?response_type=code&scope=openid+signicat.idin&client_id=demo-preprod-basic&redirect_uri='.$request->getUri().'&acr_values=urn:signicat:oidc:method:idin-ident&state=123');
    }

    /**
     * @Route("/logout")
     * @Template
     */
    public function logoutAction(Session $session, Request $request)
    {
        $session->set('requestType', null);
        $session->set('request', null);
        $session->set('contact', null);
        $session->set('organisation', null);

        return $this->redirect($this->generateUrl('app_default_index'));
    }

    /**
     * @Route("/register")
     * @Template
     */
    public function registerAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Get resource
        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => getenv('APP_ID')]);
        $variables['userGroups'] = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $application['organization']['@id'], 'canBeRegisteredFor' => true])['hydra:member'];
        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            $email = [];
            $contact = [];
            $user = [];

            //create the email in CC
            $email['name'] = 'userEmail';
            $email['email'] = $resource['email'];
            $email = $commonGroundService->createResource($email, ['component' => 'cc', 'type' => 'emails']);

            //create the contact in CC
            if (array_key_exists('achternaam', $resource)) {
                if (array_key_exists('tussenvoegsel', $resource)) {
                    $contact['additionalName'] = $resource['tussenvoegsel'];
                }
                $contact['familyName'] = $resource['achternaam'];
            }
            foreach ($resource['userGroups'] as $userGroupUrl) { //check the selected group(s)
                $userGroup = $commonGroundService->getResource($userGroupUrl); //get the group resource
                if ($userGroup['name'] == 'Studenten') { //check if the group studenten is selected
                    $contact['name'] = 'studentUserContact';
                    if (array_key_exists('voornaam', $resource) && !empty($resource['voornaam'])) {
                        $contact['givenName'] = $resource['voornaam'];
                    } else {
                        $contact['givenName'] = 'studentUserContact';
                    }
                    $contact['emails'] = [];
                    $contact['emails'][0] = $email['@id'];
                    $contact = $commonGroundService->createResource($contact, ['component' => 'cc', 'type' => 'people']); //create a person in CC

                    //create the participant in EDU
                    $participant = [];
                    $participant['person'] = $contact['@id'];
                    $commonGroundService->createResource($participant, ['component' => 'edu', 'type' => 'participants']);

                    //create the employee in MRC
                    $employee = [];
                    $employee['person'] = $contact['@id'];
                    $employee['organization'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'organizations']);
                    $commonGroundService->createResource($employee, ['component' => 'mrc', 'type' => 'employees']);
                } elseif ($userGroup['name'] == 'Bedrijven') { //check if the group bedrijven is selected
                    $contactPerson = [];
                    $contactPerson['name'] = 'bedrijfUserContact';
                    if (array_key_exists('voornaam', $resource) && !empty($resource['voornaam'])) {
                        $contactPerson['givenName'] = $resource['voornaam'];
                    } else {
                        $contactPerson['givenName'] = 'bedrijfUserContact';
                    }
                    $contactPerson['emails'] = [];
                    $contactPerson['emails'][0] = $email['@id'];
                    $contactPerson = $commonGroundService->createResource($contactPerson, ['component' => 'cc', 'type' => 'people']); //create a person in CC

                    //create an organization in CC
                    $contact['name'] = 'bedrijfUserContact';
                    $contact['description'] = 'Beschrijving van dit bedrijfUserContact';
                    $contact['type'] = 'Participant';
                    $contact['emails'] = [];
                    $contact['emails'][0] = $email['@id'];
                    $contact['persons'] = [];
                    $contact['persons'][0] = $contactPerson['@id'];
                    $contact = $commonGroundService->createResource($contact, ['component' => 'cc', 'type' => 'organizations']);

                    //create an organization in WRC
//                    $organization = [];
//                    $organization['name'] = 'bedrijfUserContact';
//                    $organization['description'] = 'Beschrijving van dit bedrijfUserContact';
//                    $organization['rsin'] = '999912345';
//                    $organization['contact'] = $contact['@id'];
//                    $commonGroundService->createResource($organization, ['component' => 'wrc', 'type' => 'organizations']);
                }
            }

            //create the user in UC
            $user['organization'] = $application['organization']['@id'];
            $user['username'] = $resource['email'];
            $user['password'] = $resource['wachtwoord'];
            $user['person'] = $contact['@id'];
            $user['userGroups'] = [];
            $user['userGroups'] = $resource['userGroups'];
            $commonGroundService->createResource($user, ['component' => 'uc', 'type' => 'users']);

            return $this->redirectToRoute('app_default_index');
        }

        return $variables;
    }
}
