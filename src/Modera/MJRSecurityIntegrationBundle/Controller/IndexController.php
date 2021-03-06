<?php

namespace Modera\MJRSecurityIntegrationBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Modera\SecurityBundle\Entity\User;
use Modera\SecurityBundle\Service\UserService;
use Modera\SecurityBundle\ModeraSecurityBundle;
use Modera\SecurityBundle\Security\Authenticator;
use Modera\SecurityBundle\DependencyInjection\ModeraSecurityExtension;
use Modera\DirectBundle\Annotation\Remote;
use Modera\MjrIntegrationBundle\Config\MainConfigInterface;
use Modera\MjrIntegrationBundle\AssetsHandling\AssetsProvider;
use Modera\MjrIntegrationBundle\ClientSideDependencyInjection\ServiceDefinitionsManager;
use Modera\MjrIntegrationBundle\DependencyInjection\ModeraMjrIntegrationExtension;
use Modera\MJRSecurityIntegrationBundle\ModeraMJRSecurityIntegrationBundle;
use Modera\MJRSecurityIntegrationBundle\DependencyInjection\ModeraMJRSecurityIntegrationExtension;
use Sli\ExpanderBundle\Ext\ContributorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entry point to web application.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class IndexController extends Controller
{
    /**
     * Entry point MF backend.
     *
     * @Route("/")
     *
     * @return array
     */
    public function indexAction()
    {
        $runtimeConfig = $this->container->getParameter(ModeraMjrIntegrationExtension::CONFIG_KEY);
        $securedRuntimeConfig = $this->container->getParameter(ModeraMJRSecurityIntegrationExtension::CONFIG_KEY);

        /* @var ContributorInterface $classLoaderMappingsProvider */
        $classLoaderMappingsProvider = $this->get('modera_mjr_integration.bootstrapping_class_loader_mappings_provider');

        /* @var MainConfigInterface $mainConfig */
        $mainConfig = $this->container->get($runtimeConfig['main_config_provider']);
        $runtimeConfig['home_section'] = $mainConfig->getHomeSection();
        $runtimeConfig['deployment_name'] = $mainConfig->getTitle();
        $runtimeConfig['deployment_url'] = $mainConfig->getUrl();
        $runtimeConfig['class_loader_mappings'] = $classLoaderMappingsProvider->getItems();

        // for docs regarding how to use "non-blocking" assets see
        // \Modera\MjrIntegrationBundle\AssetsHandling\AssetsProvider class

        /* @var AssetsProvider $assetsProvider */
        $assetsProvider = $this->get('modera_mjr_integration.assets_handling.assets_provider');

        /* @var RouterInterface $router */
        $router = $this->get('router');
        // converting URL like /app_dev.php/backend/ModeraFoundation/Application.js to /app_dev.php/backend/ModeraFoundation
        $appLoadingPath = $router->generate('modera_mjr_security_integration.index.application');
        $appLoadingPath = substr($appLoadingPath, 0, strpos($appLoadingPath, 'Application.js') - 1);

        /* @var Kernel $kernel */
        $kernel = $this->get('kernel');

        $content = $this->renderView(
            'ModeraMJRSecurityIntegrationBundle:Index:index.html.twig',
            array(
                'config' => array_merge($runtimeConfig, $securedRuntimeConfig),
                'css_resources' => $assetsProvider->getCssAssets(AssetsProvider::TYPE_BLOCKING),
                'js_resources' => $assetsProvider->getJavascriptAssets(AssetsProvider::TYPE_BLOCKING),
                'app_loading_path' => $appLoadingPath,
                'disable_caching' => $kernel->getEnvironment() != 'prod',
            )
        );

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/html');

        return $response;
    }

    /**
     * Dynamically generates an entry point to backend application, action's output is JavaScript class
     * which is used by ExtJs to bootstrap application.
     *
     * @see Resources/config/routing.yml
     * @see \Modera\MJRSecurityIntegrationBundle\Contributions\RoutingResourcesProvider
     */
    public function applicationAction()
    {
        /* @var AssetsProvider $assetsProvider */
        $assetsProvider = $this->get('modera_mjr_integration.assets_handling.assets_provider');

        $nonBlockingResources = array(
            'css' => $assetsProvider->getCssAssets(AssetsProvider::TYPE_NON_BLOCKING),
            'js' => $assetsProvider->getJavascriptAssets(AssetsProvider::TYPE_NON_BLOCKING),
        );

        /* @var ServiceDefinitionsManager $definitionsMgr */
        $definitionsMgr = $this->container->get('modera_mjr_integration.csdi.service_definitions_manager');
        $content = $this->renderView(
            'ModeraMJRSecurityIntegrationBundle:Index:application.html.twig',
            array(
                'non_blocking_resources' => $nonBlockingResources,
                'container_services' => $definitionsMgr->getDefinitions(),
                'config' => $this->container->getParameter(ModeraMjrIntegrationExtension::CONFIG_KEY),
            )
        );

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/javascript');

        return $response;
    }

    /**
     * Endpoint can be used by MJR to figure out if user is already authenticated and therefore
     * runtime UI can be loaded.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function isAuthenticatedAction(Request $request)
    {
        $this->initSession($request);

        /* @var TokenStorageInterface $sc */
        $sc = $this->get('security.token_storage');
        $token = $sc->getToken();

        $response = Authenticator::getAuthenticationResponse($token);

        if ($response['success']) {
            if (!$this->isGranted(ModeraMJRSecurityIntegrationBundle::ROLE_BACKEND_USER, $token->getUser())) {
                $response = array(
                    'success' => false,
                    'message' => "You don't have required rights to access administration interface.",
                );
            }
        }

        return new JsonResponse($response);
    }

    /**
     * @param Request $request
     * @param string  $username
     * @return RedirectResponse
     */
    public function switchUserToAction(Request $request, $username)
    {
        $url = '/';

        $switchUserConfig = $this->container->getParameter(ModeraSecurityExtension::CONFIG_KEY . '.switch_user');
        if ($switchUserConfig) {
            $parameters = array();
            $parameters[$switchUserConfig['parameter']] = $username;
            $url = $this->generateUrl('modera_mjr_security_integration.index.is_authenticated', $parameters);
        }

        return $this->redirect($url);
    }

    /**
     * @Remote
     */
    public function backendUsersListAction(array $params)
    {
        $role = ModeraSecurityBundle::ROLE_ROOT_USER;
        if ($switchUserConfig = $this->container->getParameter(ModeraSecurityExtension::CONFIG_KEY . '.switch_user')) {
            $role = $switchUserConfig['role'];
        }
        $this->denyAccessUnlessGranted($role);

        /* @var UserService $userService */
        $userService = $this->get('modera_security.service.user_service');

        $user = $this->getUser();
        $rootUser = $userService->getRootUser();

        $qb = $this->em()->createQueryBuilder();
        $qb->select('partial u.{id, firstName, lastName, username}')
            ->from(User::clazz(), 'u')
            ->leftJoin('u.permissions', 'up')
            ->leftJoin('u.groups', 'g')
            ->leftJoin('g.permissions', 'gp')
            ->where($qb->expr()->eq('u.isActive', ':isActive'))
                ->setParameter('isActive', true)
            ->andWhere($qb->expr()->notIn('u.id', [ $user->getId(), $rootUser->getId() ]))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('up.roleName', ':roleName'),
                    $qb->expr()->in('gp.roleName', ':roleName')
                )
            )
                ->setParameter('roleName', ModeraMJRSecurityIntegrationBundle::ROLE_BACKEND_USER)
            ->groupBy('u.id')
        ;

        if (isset($params['sort'])) {
            foreach ($params['sort'] as $sort) {
                $qb->orderBy('u.' . $sort['property'], $sort['direction']);
            }
        }

        if (isset($params['filter'])) {
            foreach ($params['filter'] as $filter) {
                if ('name' === $filter['property']) {
                    $qb->andWhere(
                        $qb->expr()->orX(
                            $qb->expr()->like('u.username', ':name'),
                            $qb->expr()->like('u.firstName', ':name'),
                            $qb->expr()->like('u.lastName', ':name')
                        )
                    )->setParameter('name', '%' . $filter['value'] . '%');
                }
            }
        }

        $start = isset($params['start']) ? $params['start'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 25;
        $qb->setFirstResult($start)->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHydrationMode($query::HYDRATE_ARRAY);
        $paginator = new Paginator($query);

        $items = array();
        $total = $paginator->count();
        if ($total) {
            foreach ($paginator as $item) {
                $items[] = $item;
            }
        }

        return array(
            'success' => true,
            'items' => $items,
            'total' => $total,
        );
    }

    /**
     * @param Request $request
     */
    private function initSession(Request $request)
    {
        $session = $request->getSession();
        if ($session instanceof Session && !$session->getId()) {
            $session->start();
        }
    }

    /**
     * @return EntityManager
     */
    private function em()
    {
        return $this->get('doctrine')->getManager();
    }
}
