<?php

// src/Controller/ProcessController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use function GuzzleHttp\Promise\all;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ProcessController
 *
 * @Route("/chin")
 */
class ChinController extends AbstractController
{
    /**
     * @Route("/user")
     * @Template
     */
    public function userAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['person' => $this->getUser()->getPerson(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/organisation")
     * @Template
     */
    public function organisationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['resources'] = $commonGroundService->getResourceList(['component'=>'brc', 'type'=>'invoices'], ['submitters.brp'=>$variables['user']['@id']])['hydra:member'];

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['receiver' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/checkin/{code}")
     * @Template
     */
    public function checkinAction(Session $session, $code = null, Request $request, FlashBagInterface $flash, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = [];
        $createCheckin = $request->request->get('createCheckin');
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }

        if ($code) {
            $session->set('code', $code);
            $variables['code'] = $code;
            $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
            if (count($variables['resources']) > 0) {
                $variables['resource'] = $variables['resources'][0];
            }
        }

        // Alleen afgaan bij post EN ingelogde gebruiker

        if ($request->isMethod('POST') && $this->getUser() && $createCheckin == 'true') {

            //update person
            $node = $request->request->get('node');
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $tel = $request->request->get('tel');

            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['person' => $person['@id']])['hydra:member'];
            $user = $user[0];

            $emailResource = $person['emails'][0];
            $emailResource['email'] = $email;
            $commonGroundService->updateResource($emailResource);

            $telephoneResource = $person['telephones'][0];
            $telephoneResource['telephone'] = $tel;
            $commonGroundService->updateResource($telephoneResource);

            //create check-in
            $checkIn = [];
            $checkIn['node'] = $node;
            $checkIn['person'] = $person['@id'];
            $checkIn['userUrl'] = $user['@id'];

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);
            $flash->add('success', 'U bent succesvol ingecheckt');

            return $this->redirect($this->generateUrl('app_chin_user', ['showCheckin'=>'true']));
        }

        return $variables;
    }
}
