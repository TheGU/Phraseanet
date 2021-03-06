<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2015 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Controller\Admin;

use Alchemy\Phrasea\Exception\SessionNotFound;
use Alchemy\Phrasea\Helper\DatabaseHelper;
use Alchemy\Phrasea\Helper\PathHelper;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Root implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $app['controller.admin.root'] = $this;

        $controllers = $app['controllers_factory'];
        $app['firewall']->addMandatoryAuthentication($controllers);

        $controllers->before(function (Request $request) use ($app) {
            $app['firewall']->requireAccessToModule('admin');
        });

        $controllers->get('/', function (Application $app, Request $request) {
            try {
                \Session_Logger::updateClientInfos($app, 3);
            } catch (SessionNotFound $e) {
                return $app->redirectPath('logout');
            }

            $section = $request->query->get('section', false);

            $available = [
                'connected',
                'registrations',
                'taskmanager',
                'base',
                'bases',
                'collection',
                'user',
                'users'
            ];

            $feature = 'connected';
            $featured = false;
            $position = explode(':', $section);
            if (count($position) > 0) {
                if (in_array($position[0], $available)) {
                    $feature = $position[0];

                    if (isset($position[1])) {
                        $featured = $position[1];
                    }
                }
            }

            $databoxes = $off_databoxes = [];
            foreach ($app['phraseanet.appbox']->get_databoxes() as $databox) {
                try {
                    if (!$app['acl']->get($app['authentication']->getUser())->has_access_to_sbas($databox->get_sbas_id())) {
                        continue;
                    }
                    $databox->get_connection();
                } catch (\Exception $e) {
                    $off_databoxes[] = $databox;
                    continue;
                }

                $databoxes[] = $databox;
            }

            $params = [
                'feature'       => $feature,
                'featured'      => $featured,
                'databoxes'     => $databoxes,
                'off_databoxes' => $off_databoxes
            ];

            return $app['twig']->render('admin/index.html.twig', [
                'module'        => 'admin',
                'events'        => $app['events-manager'],
                'module_name'   => 'Admin',
                'notice'        => $request->query->get("notice"),
                'feature'       => $feature,
                'featured'      => $featured,
                'databoxes'     => $databoxes,
                'off_databoxes' => $off_databoxes,
                'tree'          => $app['twig']->render('admin/tree.html.twig', $params),
            ]);
        })->bind('admin');

        $controllers->get('/tree/', function (Application $app, Request $request) {
            try {
                \Session_Logger::updateClientInfos($app, 3);
            } catch (SessionNotFound $e) {
                return $app->redirectPath('logout');
            }

            $available = [
                'connected',
                'registrations',
                'taskmanager',
                'base',
                'bases',
                'collection',
                'user',
                'users'
            ];

            $feature = 'connected';
            $featured = false;

            $position = explode(':', $request->query->get('position', false));
            if (count($position) > 0) {
                if (in_array($position[0], $available)) {
                    $feature = $position[0];

                    if (isset($position[1])) {
                        $featured = $position[1];
                    }
                }
            }

            $databoxes = $off_databoxes = [];
            foreach ($app['phraseanet.appbox']->get_databoxes() as $databox) {
                try {
                    if (!$app['acl']->get($app['authentication']->getUser())->has_access_to_sbas($databox->get_sbas_id())) {
                        continue;
                    }

                    $databox->get_connection();
                } catch (\Exception $e) {
                    $off_databoxes[] = $databox;
                    continue;
                }

                $databoxes[] = $databox;
            }

            $params = [
                'feature'       => $feature,
                'featured'      => $featured,
                'databoxes'     => $databoxes,
                'off_databoxes' => $off_databoxes
            ];

            return $app['twig']->render('admin/tree.html.twig', $params);
        })->bind('admin_display_tree');

        $controllers->get('/test-paths/', function (Application $app, Request $request) {
            if (!$request->isXmlHttpRequest()) {
                $app->abort(400);
            }
            if (!array_key_exists($request->getMimeType('json'), array_flip($request->getAcceptableContentTypes()))) {
                $app->abort(400, $app->trans('Bad request format, only JSON is allowed'));
            }

            if (0 === count($tests = $request->query->get('tests', []))) {
                $app->abort(400, $app->trans('Missing tests parameter'));
            }

            if (null === $path = $request->query->get('path')) {
                $app->abort(400, $app->trans('Missing path parameter'));
            }

            foreach ($tests as $test) {
                switch ($test) {
                    case 'writeable':
                        $result = is_writable($path);
                        break;
                    case 'readable':
                    default:
                    $result = is_readable($path);
                }
            }

            return $app->json(['results' => $result]);
        })
            ->bind('admin_test_paths');

        $controllers->get('/structure/{databox_id}/', function (Application $app, Request $request, $databox_id) {
            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            $databox = $app['phraseanet.appbox']->get_databox((int) $databox_id);
            $structure = $databox->get_structure();
            $errors = \databox::get_structure_errors($app['translator'], $structure);

            if ($updateOk = !!$request->query->get('success', false)) {
                $updateOk = true;
            }

            if (false !== $errorsStructure = $request->query->get('error', false)) {
                $errorsStructure = true;
            }

            return $app['twig']->render('admin/structure.html.twig', [
                'databox'         => $databox,
                'errors'          => $errors,
                'structure'       => $structure,
                'errorsStructure' => $errorsStructure,
                'updateOk'        => $updateOk
            ]);
        })->assert('databox_id', '\d+')
          ->bind('database_display_stucture');

        $controllers->post('/structure/{databox_id}/', function (Application $app, Request $request, $databox_id) {
            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            if (null === $structure = $request->request->get('structure')) {
                $app->abort(400, $app->trans('Missing "structure" parameter'));
            }

            $errors = \databox::get_structure_errors($app['translator'], $structure);

            $domst = new \DOMDocument('1.0', 'UTF-8');
            $domst->preserveWhiteSpace = false;
            $domst->formatOutput = true;

            if (count($errors) == 0 && $domst->loadXML($structure)) {
                $databox = $app['phraseanet.appbox']->get_databox($databox_id);
                $databox->saveStructure($domst);

                return $app->redirectPath('database_display_stucture', ['databox_id' => $databox_id, 'success' => 1]);
            } else {
                return $app->redirectPath('database_display_stucture', ['databox_id' => $databox_id, 'success' => 0, 'error' => 'struct']);
            }
        })->assert('databox_id', '\d+')
          ->bind('database_submit_stucture');

        $controllers->get('/statusbit/{databox_id}/', function (Application $app, Request $request, $databox_id) {
            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            return $app['twig']->render('admin/statusbit.html.twig', [
                'databox' => $app['phraseanet.appbox']->get_databox($databox_id),
            ]);
        })->assert('databox_id', '\d+')
          ->bind('database_display_statusbit');

        $controllers->get('/statusbit/{databox_id}/status/{bit}/', function (Application $app, Request $request, $databox_id, $bit) {
            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            $databox = $app['phraseanet.appbox']->get_databox($databox_id);

            $statusStructure = $databox->getStatusStructure();

            switch ($errorMsg = $request->query->get('error')) {
                case 'rights':
                    $errorMsg = $app->trans('You do not enough rights to update status');
                    break;
                case 'too-big':
                    $errorMsg = $app->trans('File is too big : 64k max');
                    break;
                case 'upload-error':
                    $errorMsg = $app->trans('Status icon upload failed : upload error');
                    break;
                case 'wright-error':
                    $errorMsg = $app->trans('Status icon upload failed : can not write on disk');
                    break;
                case 'unknow-error':
                    $errorMsg = $app->trans('Something wrong happend');
                    break;
            }

            if ($statusStructure->hasStatus($bit)) {
                $status = $statusStructure->getStatus($bit);
            } else {
                $status = [
                    "labeloff" => '',
                    "labelon" => '',
                    "img_off" => '',
                    "img_on" => '',
                    "path_off" => '',
                    "path_on" => '',
                    "searchable" => false,
                    "printable" => false,
                ];

                foreach ($app['locales.available'] as $code => $language) {
                    $status['labels_on'][$code] = null;
                    $status['labels_off'][$code] = null;
                }
            }

            return $app['twig']->render('admin/statusbit/edit.html.twig', [
                'status' => $status,
                'errorMsg' => $errorMsg
            ]);
        })->assert('databox_id', '\d+')
          ->assert('bit', '\d+')
          ->bind('database_display_statusbit_form');

        $controllers->post('/statusbit/{databox_id}/status/{bit}/delete/', function (Application $app, Request $request, $databox_id, $bit) {
            if (!$request->isXmlHttpRequest() || !array_key_exists($request->getMimeType('json'), array_flip($request->getAcceptableContentTypes()))) {
                $app->abort(400, $app->trans('Bad request format, only JSON is allowed'));
            }

            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            $databox = $app['phraseanet.appbox']->get_databox($databox_id);

            $error = false;

            try {
                $app['status.provider']->deleteStatus($databox->getStatusStructure(), $bit);
            } catch (\Exception $e) {
                $error = true;
            }

            return $app->json(['success' => !$error]);
        })
            ->bind('admin_statusbit_delete')
            ->assert('databox_id', '\d+')
            ->assert('bit', '\d+');

        $controllers->post('/statusbit/{databox_id}/status/{bit}/', function (Application $app, Request $request, $databox_id, $bit) {
            if (!$app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($databox_id, 'bas_modify_struct')) {
                $app->abort(403);
            }

            $properties = [
                'searchable' => $request->request->get('searchable') ? '1' : '0',
                'printable'  => $request->request->get('printable') ? '1' : '0',
                'name'       => $request->request->get('name', ''),
                'labelon'    => $request->request->get('label_on', ''),
                'labeloff'   => $request->request->get('label_off', ''),
                'labels_on'  => $request->request->get('labels_on', []),
                'labels_off' => $request->request->get('labels_off', []),
            ];

            $databox = $app['phraseanet.appbox']->get_databox($databox_id);

            $app['status.provider']->updateStatus($databox->getStatusStructure(), $bit, $properties);

            if (null !== $request->request->get('delete_icon_off')) {
                \databox_status::deleteIcon($app, $databox_id, $bit, 'off');
            }

            if (null !== $file = $request->files->get('image_off')) {
                try {
                    \databox_status::updateIcon($app, $databox_id, $bit, 'off', $file);
                } catch (AccessDeniedHttpException $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'rights',
                    ]);
                } catch (\Exception_InvalidArgument $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'unknow-error',
                    ]);
                } catch (\Exception_Upload_FileTooBig $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'too-big',
                    ]);
                } catch (\Exception_Upload_Error $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'upload-error',
                    ]);
                } catch (\Exception_Upload_CannotWriteFile $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'wright-error',
                    ]);
                } catch (\Exception $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'unknow-error',
                    ]);
                }
            }

            if (null !== $request->request->get('delete_icon_on')) {
                \databox_status::deleteIcon($app, $databox_id, $bit, 'on');
            }

            if (null !== $file = $request->files->get('image_on')) {
                try {
                    \databox_status::updateIcon($app, $databox_id, $bit, 'on', $file);
                } catch (AccessDeniedHttpException $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'rights',
                    ]);
                } catch (\Exception_InvalidArgument $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'unknow-error',
                    ]);
                } catch (\Exception_Upload_FileTooBig $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'too-big',
                    ]);
                } catch (\Exception_Upload_Error $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'upload-error',
                    ]);
                } catch (\Exception_Upload_CannotWriteFile $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'wright-error',
                    ]);
                } catch (\Exception $e) {
                    return $app->redirectPath('database_display_statusbit_form', [
                        'databox_id' => $databox_id,
                        'bit'        => $bit,
                        'error'      => 'unknow-error',
                    ]);
                }
            }

            return $app->redirectPath('database_display_statusbit', ['databox_id' => $databox_id, 'success' => 1]);
        })->assert('databox_id', '\d+')
          ->assert('bit', '\d+')
          ->bind('database_submit_statusbit');

        return $controllers;
    }
}
