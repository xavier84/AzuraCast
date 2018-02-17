<?php
namespace Controller\Api\Stations;

use App\Http\Request;
use App\Http\Response;
use Azuracast\Radio;
use AzuraCast\Radio\Configuration;
use Doctrine\ORM\EntityManager;
use Entity;

class ServicesController
{
    /** @var EntityManager */
    protected $em;

    /** @var Configuration */
    protected $configuration;

    public function __construct(EntityManager $em, Configuration $configuration)
    {
        $this->em = $em;
        $this->configuration = $configuration;
    }

    /**
     * @SWG\Post(path="/station/{station_id}/restart",
     *   tags={"Stations: Service Control"},
     *   description="Restart all services associated with the radio broadcast.",
     *   @SWG\Parameter(ref="#/parameters/station_id_required"),
     *   @SWG\Response(response=200, description="Success", @SWG\Schema(ref="#/definitions/Status")),
     *   @SWG\Response(response=403, description="Access Forbidden", @SWG\Schema(ref="#/definitions/Error")),
     *   security={
     *     {"api_key": {"manage station broadcasting"}}
     *   }
     * )
     */
    public function restartAction(Request $request, Response $response): Response
    {
        /** @var Entity\Station $station */
        $station = $request->getAttribute('station');

        /** @var Radio\Backend\BackendAbstract $backend */
        $backend = $request->getAttribute('station_backend');

        /** @var Radio\Frontend\FrontendAbstract $frontend */
        $frontend = $request->getAttribute('station_frontend');

        $this->configuration->writeConfiguration($station);

        $backend->stop();
        $frontend->stop();

        $frontend->start();
        $backend->start();

        $station->setHasStarted(true);
        $station->setNeedsRestart(false);

        $this->em->persist($station);
        $this->em->flush();

        return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s restarted.'), _('Station'))));
    }

    /**
     * @SWG\Post(path="/station/{station_id}/frontend/{action}",
     *   tags={"Stations: Service Control"},
     *   description="Perform service control actions on the radio frontend (Icecast, SHOUTcast, etc.)",
     *   @SWG\Parameter(ref="#/parameters/station_id_required"),
     *   @SWG\Parameter(
     *     name="action",
     *     description="The action to perform (start, stop, restart)",
     *     type="string",
     *     format="string",
     *     in="path",
     *     default="restart",
     *     required=false
     *   ),
     *   @SWG\Response(response=200, description="Success", @SWG\Schema(ref="#/definitions/Status")),
     *   @SWG\Response(response=403, description="Access Forbidden", @SWG\Schema(ref="#/definitions/Error")),
     *   security={
     *     {"api_key": {"manage station broadcasting"}}
     *   }
     * )
     */
    public function frontendAction(Request $request, Response $response, $station_id, $do = 'restart'): Response
    {
        /** @var Radio\Frontend\FrontendAbstract $frontend */
        $frontend = $request->getAttribute('station_frontend');

        switch ($do) {
            case "stop":
                $frontend->stop();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s stopped.'), _('Frontend'))));
            break;

            case "start":
                $frontend->start();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s started.'), _('Frontend'))));
            break;

            case "restart":
            default:
                $frontend->stop();
                $frontend->write();
                $frontend->start();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s restarted.'), _('Frontend'))));
            break;
        }
    }

    /**
     * @SWG\Post(path="/station/{station_id}/backend/{action}",
     *   tags={"Stations: Service Control"},
     *   description="Perform service control actions on the radio backend (Liquidsoap)",
     *   @SWG\Parameter(ref="#/parameters/station_id_required"),
     *   @SWG\Parameter(
     *     name="action",
     *     description="The action to perform (start, stop, restart)",
     *     type="string",
     *     format="string",
     *     in="path",
     *     default="restart",
     *     required=false
     *   ),
     *   @SWG\Response(response=200, description="Success", @SWG\Schema(ref="#/definitions/Status")),
     *   @SWG\Response(response=403, description="Access Forbidden", @SWG\Schema(ref="#/definitions/Error")),
     *   security={
     *     {"api_key": {"manage station broadcasting"}}
     *   }
     * )
     */
    public function backendAction(Request $request, Response $response, $station_id, $do = 'restart'): Response
    {
        /** @var Radio\Backend\BackendAbstract $backend */
        $backend = $request->getAttribute('station_backend');

        switch ($do) {
            case "skip":
                if (method_exists($backend, 'skip')) {
                    $backend->skip();
                }

                return $response->withJson(new Entity\Api\Status(true, _('Song skipped.')));
            break;

            case "stop":
                $backend->stop();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s stopped.'), _('Backend'))));
                break;

            case "start":
                $backend->start();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s started.'), _('Backend'))));
                break;

            case "restart":
            default:
                $backend->stop();
                $backend->write();
                $backend->start();

                return $response->withJson(new Entity\Api\Status(true, sprintf(_('%s restarted.'), _('Backend'))));
                break;
        }
    }

}