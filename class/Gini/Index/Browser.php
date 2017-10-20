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
        $this->server->on('onHTMLActionsPanel', [$this, 'htmlActionsPanel'], 200);
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
                        $subNodes[] = [
                            'node' => $node,
                            'path' => $path,
                            'name' => $name,
                            'type' => $props['{DAV:}resourcetype']->getValue(),
                            'mtime' => $props['{DAV:}getlastmodified']->getTime()->format('Y-m-d H:i:s'),
                        ];
                    }
                });
            } else {
                throw new DAV\Exception\NotFound('Not a Directory!');
            }
        } catch (DAV\Exception\NotFound $e) {
            // We're simply stopping when the file isn't found to not interfere
            // with other plugins.
            return false;
        }

        $response->setStatus(200);
        $response->setHeader('Content-Type', 'text/html; charset=utf-8');

        $view = V('home', [
            'title' => 'Gini Index',
            'baseUri' => $this->server->getBaseUri(),
            'node' => $node,
            'path' => $path,
            'subNodes' => $subNodes
        ]);
        $response->setBody((string)$view);

        return false;
    }

    /**
     * Escapes a string for html.
     *
     * @param string $value
     * @return string
     */
    public function escapeHTML($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generates the html directory index for a given url
     *
     * @param string $path
     * @return string
     */
    public function generateDirectoryIndex($path)
    {
        $node = $this->server->tree->getNodeForPath($path);
        if ($node instanceof DAV\ICollection) {
            $html.="<section><h1>Nodes</h1>\n";
            $html.="<table class=\"nodeTable\">";

            $subNodes = $this->server->getPropertiesForChildren($path, [
                '{DAV:}displayname',
                '{DAV:}resourcetype',
                '{DAV:}getcontenttype',
                '{DAV:}getcontentlength',
                '{DAV:}getlastmodified',
            ]);

            foreach ($subNodes as $subPath=>$subProps) {
                $subNode = $this->server->tree->getNodeForPath($subPath);
                $fullPath = $this->server->getBaseUri() . URLUtil::encodePath($subPath);
                list(, $displayPath) = URLUtil::splitPath($subPath);

                $subNodes[$subPath]['subNode'] = $subNode;
                $subNodes[$subPath]['fullPath'] = $fullPath;
                $subNodes[$subPath]['displayPath'] = $displayPath;
            }
            uasort($subNodes, [$this, 'compareNodes']);

            foreach ($subNodes as $subProps) {
                $type = [
                    'string' => 'Unknown',
                    'icon'   => 'cog',
                ];
                if (isset($subProps['{DAV:}resourcetype'])) {
                    $type = $this->mapResourceType($subProps['{DAV:}resourcetype']->getValue(), $subProps['subNode']);
                }

                $html.= '<tr>';
                $html.= '<td class="nameColumn"><a href="' . $this->escapeHTML($subProps['fullPath']) . '"><span class="oi" data-glyph="'.$this->escapeHTML($type['icon']).'"></span> ' . $this->escapeHTML($subProps['displayPath']) . '</a></td>';
                $html.= '<td class="typeColumn">' . $this->escapeHTML($type['string']) . '</td>';
                $html.= '<td>';
                if (isset($subProps['{DAV:}getcontentlength'])) {
                    $html.=$this->escapeHTML($subProps['{DAV:}getcontentlength'] . ' bytes');
                }
                $html.= '</td><td>';
                if (isset($subProps['{DAV:}getlastmodified'])) {
                    $lastMod = $subProps['{DAV:}getlastmodified']->getTime();
                    $html.=$this->escapeHTML($lastMod->format('F j, Y, g:i a'));
                }
                $html.= '</td></tr>';
            }

            $html.= '</table>';
        }

        $html.="</section>";
        $html.="<section><h1>Properties</h1>";
        $html.="<table class=\"propTable\">";

        // Allprops request
        $propFind = new \Sabre\DAV\Browser\PropFindAll($path);
        $properties = $this->server->getPropertiesByNode($propFind, $node);

        $properties = $propFind->getResultForMultiStatus()[200];

        foreach ($properties as $propName => $propValue) {
            $html.=$this->drawPropertyRow($propName, $propValue);
        }


        $html.="</table>";
        $html.="</section>";

        /* Start of generating actions */

        $output = '';
        if ($this->enablePost) {
            $this->server->emit('onHTMLActionsPanel', [$node, &$output]);
        }

        if ($output) {
            $html.="<section><h1>Actions</h1>";
            $html.="<div class=\"actions\">\n";
            $html.=$output;
            $html.="</div>\n";
            $html.="</section>\n";
        }

        $html.= "
        <footer>Generated by SabreDAV " . $version . " (c)2007-2014 <a href=\"http://sabre.io/\">http://sabre.io/</a></footer>
        </body>
        </html>";

        $this->server->httpResponse->setHeader('Content-Security-Policy', "img-src 'self'; style-src 'self';");

        return $html;
    }

    /**
     * This method is used to generate the 'actions panel' output for
     * collections.
     *
     * This specifically generates the interfaces for creating new files, and
     * creating new directories.
     *
     * @param DAV\INode $node
     * @param mixed $output
     * @return void
     */
    public function htmlActionsPanel(DAV\INode $node, &$output)
    {
        if (!$node instanceof DAV\ICollection) {
            return;
        }

        // We also know fairly certain that if an object is a non-extended
        // SimpleCollection, we won't need to show the panel either.
        if (get_class($node)==='Sabre\\DAV\\SimpleCollection') {
            return;
        }

        ob_start();
        echo '<form method="post" action="">
        <h3>Create new folder</h3>
        <input type="hidden" name="sabreAction" value="mkcol" />
        <label>Name:</label> <input type="text" name="name" /><br />
        <input type="submit" value="create" />
        </form>
        <form method="post" action="" enctype="multipart/form-data">
        <h3>Upload file</h3>
        <input type="hidden" name="sabreAction" value="put" />
        <label>Name (optional):</label> <input type="text" name="name" /><br />
        <label>File:</label> <input type="file" name="file" /><br />
        <input type="submit" value="upload" />
        </form>
        ';

        $output.=ob_get_clean();
    }

    /**
     * This method takes a path/name of an asset and turns it into url
     * suiteable for http access.
     *
     * @param string $assetName
     * @return string
     */
    protected function getAssetUrl($assetName)
    {
        return $this->server->getBaseUri() . '?sabreAction=asset&assetName=' . urlencode($assetName);
    }

    /**
     * This method returns a local pathname to an asset.
     *
     * @param string $assetName
     * @return string
     * @throws DAV\Exception\NotFound
     */
    protected function getLocalAssetPath($assetName)
    {
        $assetDir = __DIR__ . '/assets/';
        $path = $assetDir . $assetName;

        // Making sure people aren't trying to escape from the base path.
        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/../') !== false || strrchr($path, '/') === '/..') {
            throw new DAV\Exception\NotFound('Path does not exist, or escaping from the base path was detected');
        }
        if (strpos(realpath($path), realpath($assetDir)) === 0 && file_exists($path)) {
            return $path;
        }
        throw new DAV\Exception\NotFound('Path does not exist, or escaping from the base path was detected');
    }

    /**
     * This method reads an asset from disk and generates a full http response.
     *
     * @param string $assetName
     * @return void
     */
    protected function serveAsset($assetName)
    {
        $assetPath = $this->getLocalAssetPath($assetName);

        // Rudimentary mime type detection
        $mime = 'application/octet-stream';
        $map = [
            'ico'  => 'image/vnd.microsoft.icon',
            'png'  => 'image/png',
            'css'  =>  'text/css',
        ];

        $ext = substr($assetName, strrpos($assetName, '.')+1);
        if (isset($map[$ext])) {
            $mime = $map[$ext];
        }

        $this->server->httpResponse->setHeader('Content-Type', $mime);
        $this->server->httpResponse->setHeader('Content-Length', filesize($assetPath));
        $this->server->httpResponse->setHeader('Cache-Control', 'public, max-age=1209600');
        $this->server->httpResponse->setStatus(200);
        $this->server->httpResponse->setBody(fopen($assetPath, 'r'));
    }

    /**
     * Sort helper function: compares two directory entries based on type and
     * display name. Collections sort above other types.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    protected function compareNodes($a, $b)
    {
        $typeA = (isset($a['{DAV:}resourcetype']))
            ? (in_array('{DAV:}collection', $a['{DAV:}resourcetype']->getValue()))
            : false;

        $typeB = (isset($b['{DAV:}resourcetype']))
            ? (in_array('{DAV:}collection', $b['{DAV:}resourcetype']->getValue()))
            : false;

        // If same type, sort alphabetically by filename:
        if ($typeA === $typeB) {
            return strnatcasecmp($a['displayPath'], $b['displayPath']);
        }
        return (($typeA < $typeB) ? 1 : -1);
    }

    /**
     * Maps a resource type to a human-readable string and icon.
     *
     * @param array $resourceTypes
     * @param INode $node
     * @return array
     */
    private function mapResourceType(array $resourceTypes, $node)
    {
        if (!$resourceTypes) {
            if ($node instanceof DAV\IFile) {
                return [
                    'string' => 'File',
                    'icon'   => 'file',
                ];
            } else {
                return [
                    'string' => 'Unknown',
                    'icon'   => 'cog',
                ];
            }
        }

        $types = [
            '{http://calendarserver.org/ns/}calendar-proxy-write' => [
                'string' => 'Proxy-Write',
                'icon'   => 'people',
            ],
            '{http://calendarserver.org/ns/}calendar-proxy-read' => [
                'string' => 'Proxy-Read',
                'icon'   => 'people',
            ],
            '{urn:ietf:params:xml:ns:caldav}schedule-outbox' => [
                'string' => 'Outbox',
                'icon'   => 'inbox',
            ],
            '{urn:ietf:params:xml:ns:caldav}schedule-inbox' => [
                'string' => 'Inbox',
                'icon'   => 'inbox',
            ],
            '{urn:ietf:params:xml:ns:caldav}calendar' => [
                'string' => 'Calendar',
                'icon'   => 'calendar',
            ],
            '{http://calendarserver.org/ns/}shared-owner' => [
                'string' => 'Shared',
                'icon'   => 'calendar',
            ],
            '{http://calendarserver.org/ns/}subscribed' => [
                'string' => 'Subscription',
                'icon'   => 'calendar',
            ],
            '{urn:ietf:params:xml:ns:carddav}directory' => [
                'string' => 'Directory',
                'icon'   => 'globe',
            ],
            '{urn:ietf:params:xml:ns:carddav}addressbook' => [
                'string' => 'Address book',
                'icon'   => 'book',
            ],
            '{DAV:}principal' => [
                'string' => 'Principal',
                'icon'   => 'person',
            ],
            '{DAV:}collection' => [
                'string' => 'Collection',
                'icon'   => 'folder',
            ],
        ];

        $info = [
            'string' => [],
            'icon' => 'cog',
        ];
        foreach ($resourceTypes as $k=> $resourceType) {
            if (isset($types[$resourceType])) {
                $info['string'][] = $types[$resourceType]['string'];
            } else {
                $info['string'][] = $resourceType;
            }
        }
        foreach ($types as $key=>$resourceInfo) {
            if (in_array($key, $resourceTypes)) {
                $info['icon'] = $resourceInfo['icon'];
                break;
            }
        }
        $info['string'] = implode(', ', $info['string']);

        return $info;
    }

    /**
     * Draws a table row for a property
     *
     * @param string $name
     * @param mixed $value
     * @return string
     */
    private function drawPropertyRow($name, $value)
    {
        $view = 'unknown';
        if (is_scalar($value)) {
            $view = 'string';
        } elseif ($value instanceof DAV\Property) {
            $mapping = [
                'Sabre\\DAV\\Property\\IHref' => 'href',
                'Sabre\\DAV\\Property\\HrefList' => 'hreflist',
                'Sabre\\DAV\\Property\\SupportedMethodSet' => 'valuelist',
                'Sabre\\DAV\\Property\\ResourceType' => 'xmlvaluelist',
                'Sabre\\DAV\\Property\\SupportedReportSet' => 'xmlvaluelist',
                'Sabre\\DAVACL\\Property\\CurrentUserPrivilegeSet' => 'xmlvaluelist',
                'Sabre\\DAVACL\\Property\\SupportedPrivilegeSet' => 'supported-privilege-set',
            ];

            $view = 'complex';
            foreach ($mapping as $class=>$val) {
                if ($value instanceof $class) {
                    $view = $val;
                    break;
                }
            }
        }

        list($ns, $localName) = DAV\XMLUtil::parseClarkNotation($name);

        $realName = $name;
        if (isset($this->server->xmlNamespaces[$ns])) {
            $name = $this->server->xmlNamespaces[$ns] . ':' . $localName;
        }

        ob_start();

        $xmlValueDisplay = function ($propName) {
            $realPropName = $propName;
            list($ns, $localName) = DAV\XMLUtil::parseClarkNotation($propName);
            if (isset($this->server->xmlNamespaces[$ns])) {
                $propName = $this->server->xmlNamespaces[$ns] . ':' . $localName;
            }
            return "<span title=\"" . $this->escapeHTML($realPropName) . "\">" . $this->escapeHTML($propName) . "</span>";
        };

        echo "<tr><th><span title=\"", $this->escapeHTML($realName), "\">", $this->escapeHTML($name), "</span></th><td>";

        switch ($view) {

            case 'href':
                echo "<a href=\"" . $this->server->getBaseUri() . $value->getHref() . '">' . $this->server->getBaseUri() . $value->getHref() . '</a>';
                break;
            case 'hreflist':
                echo implode('<br />', array_map(function ($href) {
                    if (stripos($href, 'mailto:')===0 || stripos($href, '/')===0 || stripos($href, 'http:')===0 || stripos($href, 'https:') === 0) {
                        return "<a href=\"" . $this->escapeHTML($href) . '">' . $this->escapeHTML($href) . '</a>';
                    } else {
                        return "<a href=\"" . $this->escapeHTML($this->server->getBaseUri() . $href) . '">' . $this->escapeHTML($this->server->getBaseUri() . $href) . '</a>';
                    }
                }, $value->getHrefs()));
                break;
            case 'xmlvaluelist':
                echo implode(', ', array_map($xmlValueDisplay, $value->getValue()));
                break;
            case 'valuelist':
                echo $this->escapeHTML(implode(', ', $value->getValue()));
                break;
            case 'supported-privilege-set':
                $traverse = function ($priv) use (&$traverse, $xmlValueDisplay) {
                    echo "<li>";
                    echo $xmlValueDisplay($priv['privilege']);
                    if (isset($priv['abstract']) && $priv['abstract']) {
                        echo " <i>(abstract)</i>";
                    }
                    if (isset($priv['description'])) {
                        echo " " . $this->escapeHTML($priv['description']);
                    }
                    if (isset($priv['aggregates'])) {
                        echo "\n<ul>\n";
                        foreach ($priv['aggregates'] as $subPriv) {
                            $traverse($subPriv);
                        }
                        echo "</ul>";
                    }
                    echo "</li>\n";
                };
                echo "<ul class=\"tree\">";
                $traverse($value->getValue(), '');
                echo "</ul>\n";
                break;
            case 'string':
                echo $this->escapeHTML($value);
                break;
            case 'complex':
                echo '<em title="' . $this->escapeHTML(get_class($value)) . '">complex</em>';
                break;
            default:
                echo '<em>unknown</em>';
                break;

        }

        return ob_get_clean();
    }
}
