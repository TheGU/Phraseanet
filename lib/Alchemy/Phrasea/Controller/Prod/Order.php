<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Controller\Prod;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class Order implements ControllerProviderInterface
{

    /**
     * {@inheritDoc}
     */
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        /**
         * List all orders
         *
         * name         : prod_orders
         *
         * description  : Display all orders
         *
         * method       : GET
         *
         * parameters   : none
         *
         * return       : HTML Response
         */
        $controllers->get('/', $this->call('displayOrders'))
            ->before(function(Request $request) use ($app) {
                $app['phraseanet.core']['Firewall']->requireOrdersAdmin($app);
            })
            ->bind('prod_orders');

        /**
         * Create a new order
         *
         * name         : prod_order_new
         *
         * description  : Create a new order
         *
         * method       : POST
         *
         * parameters   : none
         *
         * return       : HTML Response | JSON Response
         */
        $controllers->post('/', $this->call('createOrder'))
            ->bind('prod_order_new');

        /**
         * Display one order
         *
         * name         : prod_order
         *
         * description  : Display one order
         *
         * method       : GET
         *
         * parameters   : none
         *
         * return       : HTML Response
         */
        $controllers->get('/{order_id}/', $this->call('displayOneOrder'))
            ->before(function(Request $request) use ($app) {
                $app['phraseanet.core']['Firewall']->requireOrdersAdmin($app);
            })
            ->bind('prod_order')
            ->assert('order_id', '\d+');

        /**
         * Send a new order
         *
         * name         : prod_order_send
         *
         * description  : Send an order
         *
         * method       : POST
         *
         * parameters   : none
         *
         * return       : HTML Response | JSON Response
         */
        $controllers->post('/{order_id}/send/', $this->call('sendOrder'))
            ->before(function(Request $request) use ($app) {
                $app['phraseanet.core']['Firewall']->requireOrdersAdmin($app);
            })
            ->bind('prod_order_send')
            ->assert('order_id', '\d+');

        /**
         * Deny an order
         *
         * name         : prod_order_deny
         *
         * description  : Deny an order
         *
         * method       : POST
         *
         * parameters   : none
         *
         * return       : HTML Response | JSON Response
         */
        $controllers->post('/{order_id}/deny/', $this->call('denyOrder'))
            ->before(function(Request $request) use ($app) {
                $app['phraseanet.core']['Firewall']->requireOrdersAdmin($app);
            })
            ->bind('prod_order_deny')
            ->assert('order_id', '\d+');

        return $controllers;
    }

    /**
     * Create a new order
     *
     * @param   Application     $app
     * @param   Request         $request
     * @param   integer         $order_id
     * @return  RedirectResponse|JsonResponse
     */
    public function createOrder(Application $app, Request $request)
    {
        $success = false;

        try {
            $order = new \set_exportorder($request->request->get('lst', ''), (int) $request->request->get('ssttid'));

            if ($order->order_available_elements(
                    $app['phraseanet.core']->getAuthenticatedUser()->get_id(), $request->request->get('use', ''), $request->request->get('deadline', '')
            )) {
                $success = true;
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }

        if ('json' === $app['request']->getRequestFormat()) {

            return $app->json(array(
                    'success' => $success,
                    'msg'     => $success ? _('The records have been properly ordered') : _('An error occured') . ' ' . $msg,
                ));
        }

        return $app->redirect($app['url_generator']->generate('prod_orders', array(
            'success' => (int) $success,
            'action'  => 'send'
        )));
    }

    /**
     * Display list of orders
     *
     * @param   Application     $app
     * @param   Request         $request
     * @param   integer         $order_id
     * @return  Response
     */
    public function displayOrders(Application $app, Request $request)
    {
        return $app['twig']->render('prod/orders/order_box.html.twig', array(
                'ordermanager' => new \set_ordermanager( ! ! $request->query->get('sort', false), (int) $request->query->get('page', 1))
            ));
    }

    /**
     * Display a single order identified by its id
     *
     * @param   Application     $app
     * @param   Request         $request
     * @param   integer         $order_id
     * @return  Response
     */
    public function displayOneOrder(Application $app, Request $request, $order_id)
    {
        try {
            $order = new \set_order($order_id);
        } catch (\Exception_NotFound $e) {
            $app->abort(404);
        }

        return $app['twig']->render('prod/orders/order_item.html.twig', array(
                'order' => $order
            ));
    }

    /**
     * Send an order
     *
     * @param   Application     $app
     * @param   Request         $request
     * @param   integer         $order_id
     * @return  RedirectResponse|JsonResponse
     */
    public function sendOrder(Application $app, Request $request, $order_id)
    {
        $success = false;

        try {
            $order = new \set_order($order_id);
        } catch (\Exception_NotFound $e) {
            $app->abort(404);
        }

        try {
            $order->send_elements($request->request->get('elements', array()),  ! ! $request->request->get('force', false));
            $success = true;
        } catch (\Exception $e) {

        }

        if ('json' === $app['request']->getRequestFormat()) {

            return $app->json(array(
                    'success'  => $success,
                    'msg'      => $success ? _('Order has been sent') : _('An error occured while sending, please retry  or contact an admin if problem persists'),
                    'order_id' => $order_id
                ));
        }

        return $app->redirect($app['url_generator']->generate('prod_orders', array(
            'success' => (int) $success,
            'action'  => 'send'
        )));
    }

    /**
     * Deny an order
     *
     * @param   Application     $app
     * @param   Request         $request
     * @param   integer         $order_id
     * @return  RedirectResponse|JsonResponse
     */
    public function denyOrder(Application $app, Request $request, $order_id)
    {
        $success = false;

        try {
            $order = new \set_order($order_id);
        } catch (\Exception_NotFound $e) {
            $app->abort(404);
        }

        try {
            $order->deny_elements($request->request->get('elements', array()));
            $success = true;
        } catch (\Exception $e) {

        }

        if ('json' === $app['request']->getRequestFormat()) {

            return $app->json(array(
                    'success'  => $success,
                    'msg'      => $success ? _('Order has been denied') : _('An error occured while denying, please retry  or contact an admin if problem persists'),
                    'order_id' => $order_id
                ));
        }

        return $app->redirect($app['url_generator']->generate('prod_orders', array(
            'success' => (int) $success,
            'action'  => 'send'
        )));
    }

    /**
     * Prefix the method to call with the controller class name
     *
     * @param  string $method The method to call
     * @return string
     */
    private function call($method)
    {
        return sprintf('%s::%s', __CLASS__, $method);
    }
}
