<?php

namespace Varspool\DisqueAdmin\Controller;

use Symfony\Component\HttpFoundation\Request;

class QueueController extends BaseController
{
    protected $columns = [
        'name',
        'len',
        'age',
        'idle',
        'blocked',
        'import-from',
        'import-rate',
        'jobs-in',
        'jobs-out',
        'pause',
    ];

    protected $format = [
        'idle' => 'formatIntervalSeconds',
        'age' => 'formatIntervalSeconds',
        'len' => 'formatJobCount',
        'jobs-in' => 'formatJobCount',
        'jobs-out' => 'formatJobCount',
    ];

    public function indexAction(Request $request)
    {
        $client = $this->getDisque($request);

        $response = $client->qscan(0, [
            'busyloop' => true,
            'minlen' => 1,
        ]);

        $queues = [];

        foreach ($response['queues'] as $queue) {
            $queues[$queue] = $this->formatObject($client->qstat($queue));
        }

        return $this->render('queue/index.html.twig', [
            'queues' => $queues,
            'columns' => $this->columns,
            'prefix' => $request->attributes->get('prefix')
        ]);
    }

    public function showAction(string $name, Request $request)
    {
        $client = $this->getDisque($request);

        $stat = $client->qstat($name);
        unset($stat['pause']);

        $jobs = $client->qpeek($name, 10);

        return $this->render('queue/show.html.twig', [
            'name' => $name,
            'stat' => $this->formatObject($stat),
            'jobs' => $jobs,
            'prefix' => $request->attributes->get('prefix')
        ]);
    }

    public function pauseComponent(string $name, ?string $prefix, Request $request)
    {
        $manager = $this->getDisque($request)->getConnectionManager();

        $nodes = $manager->getNodes();
        $currentId = $manager->getCurrentNode()->getId();

        $states = [];

        foreach ($nodes as $id => $node) {
            $client = ($this->disqueFactory)($id);
            $stat = $client->qstat($name);
            $states[$id] = $stat['pause'];
        }

        return $this->render('queue/_pause.html.twig', [
            'prefix' => $prefix,
            'states' => $states,
            'currentId' => $currentId,
        ]);
    }

    public function countsComponent(?string $prefix, Request $request)
    {
        $client = $this->getDisque($request);

        $response = $client->qscan(0, [
            'busyloop' => true,
            'minlen' => 1,
        ]);

        $queues = [];

        foreach ($response['queues'] as $queue) {
            $queues[$queue] = $client->qlen($queue);
        }

        return $this->render('queue/_counts.html.twig', [
            'queues' => $queues,
            'prefix' => $prefix
        ]);
    }
}
