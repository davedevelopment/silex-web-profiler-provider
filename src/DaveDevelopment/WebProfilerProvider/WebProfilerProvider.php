<?php

namespace DaveDevelopment\WebProfilerProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;

class WebProfilerProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        /**
         * Profiler Storage
         *
         * Could have factories for all available, requiring just requiring 
         * configs
         */
        $app['web_profiler.profiler_storage.file.dsn'] = "file:" . sys_get_temp_dir() . "/webprofiler";
        $app['web_profiler.profiler_storage.file'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\Profiler\FileProfilerStorage(
                $app['web_profiler.profiler_storage.file.dsn']
            );
        });

        // this will be used, default to file
        $app['web_profiler.profiler_storage.type'] = 'file';

        /**
         * Data Collectors
         *
         * where key is the id, and the value is an array of id, template, 
         * serviceId
         */
        $app['web_profiler.data_collectors'] = $app->share(function ($app) {
            $collectors = array(
                "config" => array("config", "@WebProfiler/Collector/config.html.twig", "web_profiler.data_collector.config"),
                "request" => array("request", "@WebProfiler/Collector/request.html.twig", "web_profiler.data_collector.request"),
                "time" => array("time", "@WebProfiler/Collector/time.html.twig", "web_profiler.data_collector.time"),
                "memory" => array("memory", "@WebProfiler/Collector/memory.html.twig", "web_profiler.data_collector.memory"),
                "events" => array("events", "@WebProfiler/Collector/events.html.twig", "web_profiler.data_collector.events"),
                "logger" => array("logger", "@WebProfiler/Collector/logger.html.twig", "web_profiler.data_collector.logger"),
                "router" => array("router", "@WebProfiler/Collector/router.html.twig", "web_profiler.data_collector.router"),
                "exception" => array("exception", "@WebProfiler/Collector/exception.html.twig", "web_profiler.data_collector.exception"),
            );

            /*
             * Doctrine 
             *
             * Nothing doing yet, the doctrine data collector requires a 
             * ManagerRegistry, which we don't have in Silex right now
             *
             */
            
            /*
             * Security is problematic
             *
             * It requires context, which isn't available until after boot, 
             * however the profiler requires the collectors, and it's required 
             * by a subscriber that is instantiated at boot. So it uses a proxy 
             * for that. 
             *
             * The templates also use the symfony way of referencing each other, 
             * which our twig doesn't understand :(
             *
            if (isset($app['security']) && class_exists("Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector")) {
                $collectors["security"] = array(
                    "security", 
                    "@Security/Collector/security.html.twig", 
                    "web_profiler.data_collector.security",
                );

                $app['web_profiler.data_collector.security.lazy_context'] = $app->share(function ($app) {
                    return new LazySecurityContext($app, 'security');
                });

                $app['web_profiler.data_collector.security'] = $app->share(function ($app) {
                    return new \Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector($app['web_profiler.data_collector.security.lazy_context']);
                });

            }
             */

            return $collectors;
        });

        $app['web_profiler.data_collector.router'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\RouterDataCollector();
        });

        $app['web_profiler.data_collector.logger'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector($app['logger']);
        });

        $app['web_profiler.data_collector.config'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector();
        });

        $app['web_profiler.data_collector.request'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\RequestDataCollector();
        });

        $app['web_profiler.data_collector.memory'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector();
        });

        $app['web_profiler.data_collector.time'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\TimeDataCollector();
        });

        $app['web_profiler.data_collector.events'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\EventDataCollector();
        });

        $app['web_profiler.data_collector.exception'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector();
        });

        /**
         * General Services
         */
        $app['web_profiler.profiler'] = $app->share(function ($app) {
            $storage = $app['web_profiler.profiler_storage.' . $app['web_profiler.profiler_storage.type']];
            $profiler = new \Symfony\Component\HttpKernel\Profiler\Profiler($storage, $app['logger']);

            foreach ($app['web_profiler.data_collectors'] as $id => $collector) {
                $profiler->add($app[$collector[2]]);
            }

            return $profiler;
        });

        $app['web_profiler.stopwatch'] = $app->share(function ($app) {
            return new \Symfony\Component\Stopwatch\Stopwatch;
        });

        $app['web_profiler.request_matcher'] = $app->share(function ($app) {
            $matcher = new \Symfony\Component\HttpFoundation\RequestMatcher();
            return $matcher;
        });


        /**
         * Controllers
         *
         * Controllers as Services is where it's at, yay for software 
         * engineering
         */
        $app['web_profiler.controller.profiler'] = $app->share(function ($app) {
            return new \Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController(
                $app['url_generator'],
                $app['web_profiler.profiler'],
                $app['twig'],
                $app['web_profiler.data_collectors'],
                $app['web_profiler.debug_toolbar.position']
            );
        });

        $app['web_profiler.controller.router'] = $app->share(function ($app) {
            return new \Symfony\Bundle\WebProfilerBundle\Controller\RouterController(
                $app['web_profiler.profiler'],
                $app['twig'],
                $app['url_matcher'],
                $app['routes']
            );
        });

        $app['web_profiler.controller.exception'] = $app->share(function ($app) {
            return new \Symfony\Bundle\WebProfilerBundle\Controller\ExceptionController(
                $app['web_profiler.profiler'],
                $app['twig'],
                $app['debug']
            );
        });

        /**
         * Listeners
         */
        $app['web_profiler.profiler_listener'] = $app->share(function ($app) {
            return new \Symfony\Component\HttpKernel\EventListener\ProfilerListener(
                $app['web_profiler.profiler'],
                $app['web_profiler.request_matcher'],
                false,
                false
            );
        });

        $app['web_profiler.debug_toolbar.position'] = 'bottom';
        $app['web_profiler.debug_toolbar'] = $app->share(function ($app) {
            return new WebDebugToolbarListener(
                $app['twig'],
                false,
                WebDebugToolbarListener::ENABLED,
                $app['web_profiler.debug_toolbar.position']
            );
        });

        /**
         * Extensions of other services
         */
        $app['dispatcher'] = $app->share($app->extend('dispatcher', function ($dispatcher, $app) {
            $tracer = new \Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher(
                $dispatcher, 
                $app['web_profiler.stopwatch'],
                $app['logger']
            );
            $tracer->setProfiler($app['web_profiler.profiler']);
            return $tracer;
        }));

        $app['twig'] = $app->share($app->extend('twig', function ($twig) {
            $twig->addExtension(new \Symfony\Bridge\Twig\Extension\CodeExtension("", __DIR__, "utf8"));
            $twig->addExtension(new \Symfony\Bridge\Twig\Extension\YamlExtension());
            return $twig;
        }));

        $app['twig.loader.filesystem'] = $app->share($app->extend('twig.loader.filesystem', function ($loader) {
            /*
             * ugh, there has to be a better way.. Perhaps we could have 
             * WebDebugToolbarListener::file akin to 
             * WebDebugToolbarListener::class
             */
            $reflClass = new \ReflectionClass("Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener");
            $loader->addPath(dirname(dirname($reflClass->getFileName())) . "/Resources/views", "WebProfiler");
            $reflClass = new \ReflectionClass("Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector");
            $loader->addPath(dirname(dirname($reflClass->getFileName())) . "/Resources/views", "Security");
            return $loader;
        }));

    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get("/search", "web_profiler.controller.profiler:searchAction")
            ->bind("_profiler_search");

        $controllers->get("/search_bar", "web_profiler.controller.profiler:searchBarAction")
            ->bind("_profiler_search_bar");

        $controllers->get("/purge", "web_profiler.controller.profiler:purgeAction")
            ->bind("_profiler_purge");

        $controllers->get("/info/{about}", "web_profiler.controller.profiler:infoAction")
            ->bind("_profiler_info");

        $controllers->get("/import", "web_profiler.controller.profiler:importAction")
            ->bind("_profiler_import");

        $controllers->get("/export", "web_profiler.controller.profiler:exportAction")
            ->bind("_profiler_export");

        $controllers->get("/phpinfo", "web_profiler.controller.profiler:phpinfoAction")
            ->bind("_profiler_phpinfo");

        $controllers->get("/{token}/search/results", "web_profiler.controller.profiler:searchResultsAction")
            ->bind("_profiler_search_results");

        $controllers->get("/{token}", "web_profiler.controller.profiler:panelAction")
            ->bind("_profiler");

        $controllers->get("/{token}/router", "web_profiler.controller.router:panelAction")
            ->bind("_profiler_router");

        $controllers->get("/{token}/exception", "web_profiler.controller.exception:showAction")
            ->bind("_profiler_exception");

        $controllers->get("/{token}/exception.css", "web_profiler.controller.exception:cssAction")
            ->bind("_profiler_exception_css");

        $controllers->get("/", function ($app) { return $app->redirect($app['url_generator']->generate('_profiler_search_results')); })
            ->bind("_profiler_redirect");

        $controllers->get("/_wdt/{token}", "web_profiler.controller.profiler:toolbarAction")
            ->bind("_wdt");

        return $controllers;
    }


    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['web_profiler.debug_toolbar']);
        $app['dispatcher']->addSubscriber($app['web_profiler.profiler_listener']);
        $app['dispatcher']->addSubscriber($app['web_profiler.data_collector.request']);
    }
}