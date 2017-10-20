<?php

namespace Gini\Index;

use
    Sabre\DAV;
use Sabre\HTTP\URLUtil;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Browser extends DAV\ServerPlugin
{

    /**
     * reference to server class
     *
     * @var Sabre\DAV\Server
     */
    protected $server;

    /**
     * Initializes the plugin and subscribes to events
     *
     * @param DAV\Server $server
     * @return void
     */
    public function initialize(DAV\Server $server)
    {
        $this->server = $server;
        $this->server->on('method:GET', [$this,'httpGet'], 200);
    }

    /**
     * This method intercepts GET requests to collections and returns the html
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     */
    public function httpGet(RequestInterface $request, ResponseInterface $response)
    {

        // We're not using straight-up $_GET, because we want everything to be
        // unit testable.
        $getVars = $request->getQueryParameters();

        $sabreAction = isset($getVars['sabreAction'])?$getVars['sabreAction']:null;

        try {
            $path = $request->getPath();
            $node = $this->server->tree->getNodeForPath($path);

            if ($node instanceof Directory) {
                $subNodes = [];
                $propsForChildren = $this->server->getPropertiesForChildren($path, [
                    '{DAV:}displayname',
                    '{DAV:}resourcetype',
                    '{DAV:}getcontenttype',
                    '{DAV:}getcontentlength',
                    '{DAV:}getlastmodified',
                ]);
                array_walk($propsForChildren, function ($props, $path) use (&$subNodes) {
                    $node = $this->server->tree->getNodeForPath($path);
                    $name = $node->getName();
                    if ($node instanceof Directory
                        || ($node instanceof File && preg_match('/.+\.tgz$/', $name))) {
                        $mtime = $props['{DAV:}getlastmodified']->getTime()
                            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                            ->format('Y-m-d H:i:s');
                        $subNodes[] = [
                            'node' => $node,
                            'path' => $path,
                            'name' => $name,
                            'type' => $props['{DAV:}resourcetype']->getValue(),
                            'mtime' => $mtime,
                        ];
                    }
                });
                usort($subNodes, function ($a, $b) {
                    return date_create($a['mtime']) < date_create($b['mtime']);
                });
            } else {
                throw new DAV\Exception\NotFound('Not a Directory!');
            }
        } catch (DAV\Exception\NotFound $e) {
            // We're simply stopping when the file isn't found to not interfere
            // with other plugins.
            return false;
        }

        // Load Log
        $logs = [];
        $maxLine = 50;
        $auditLogFile = \Gini\Logger\IndexLog::logFile();
        $fh = fopen($auditLogFile, 'r');
        if ($fh) {
            $pos = -2;
            $currentLine = '';
            while (-1 !== fseek($fh, $pos, SEEK_END)) {
                $char = fgetc($fh);
                if (PHP_EOL == $char) {
                    $logs[] = $this->parseLogFromLine($currentLine);
                    $currentLine = '';
                    if (count($logs) >= $maxLine) {
                        break;
                    }
                } else {
                    $currentLine = $char . $currentLine;
                }
                $pos--;
            }
            $currentLine and $logs[] = $this->parseLogFromLine($currentLine); // Grab final line
        }

        $response->setStatus(200);
        $response->setHeader('Content-Type', 'text/html; charset=utf-8');

        $view = V('home', [
            'title' => 'Gini Index',
            'baseUri' => $this->server->getBaseUri(),
            'node' => $node,
            'path' => $path,
            'subNodes' => $subNodes,
            'logs' => $logs
        ]);
        $response->setBody((string)$view);

        return false;
    }

    private function parseLogFromLine($line)
    {
        $log = json_decode($line, true);
        $log['timestamp'] = date_create($log['timestamp'])
            ->setTimezone(new \DateTimeZone(date_default_timezone_get())) ;
        return $log;
    }
}
