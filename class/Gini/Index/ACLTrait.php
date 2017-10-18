<?php

namespace Gini\Index;

use \Sabre\HTTP\URLUtil;

trait ACLTrait
{

    /**
     * Returns the owner principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getOwner()
    {
        return null;
    }
    
    /**
     * Returns a group principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getGroup()
    {
        return null;
    }

    /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
    public function getACL()
    {
        $moduleRoot = \Gini\Module\GiniIndex::modulePath();
        $path = \Gini\File::relativePath($this->path, $moduleRoot);
        if (!$path) {
            return [
                ['privilege' => '{DAV:}read', 'principal' => '{DAV:}all', 'protected' => true],
                ['privilege' => '{DAV:}bind', 'principal' => '{DAV:}authenticated', 'protected' => true]
            ];
        }

        list($module, ) = explode('/', $path, 2);
        if ($this instanceof File) {
            if ($module == $path || basename($path) == 'index.json') {
                return [['privilege' => '{DAV:}read', 'principal' => '{DAV:}all', 'protected' => true]];
            }
        }

        $aclConf = \Gini\Module\GiniIndex::aclConfig();
        $conf = [(array) $aclConf['_']];
        $acl = [];

        isset($aclConf[$module]) and array_unshift($conf, (array) $aclConf[$module]);

        foreach ($conf as $c) {
            foreach ((array) $c as $user => $mode) {
                if ($user == '.anonymous') {
                    $principal = '{DAV:}unauthenticated';
                } elseif ($user == '.authenticated') {
                    $principal = '{DAV:}authenticated';
                } elseif ($user == '.all') {
                    $principal = '{DAV:}all';
                } else {
                    $principal = 'principals/'.$user;
                }
                if (strstr($mode, 'r')) {
                    $acl[] = [
                        'privilege' => '{DAV:}read',
                        'principal' => $principal,
                        'protected' => true,
                    ];
                }
                if (strstr($mode, 'w')) {
                    $acl[] = [
                        'privilege' => '{DAV:}write',
                        'principal' => $principal,
                        'protected' => true,
                    ];
                }
            }
        }

        return $acl;
    }

    /**
     * Updates the ACL
     *
     * This method will receive a list of new ACE's as an array argument.
     *
     * @param array $acl
     * @return void
     */
    public function setACL(array $acl)
    {
        throw new \Sabre\DAV\Exception\Forbidden('Not allowed to change ACL');
    }

    /**
     * Returns the list of supported privileges for this node.
     *
     * The returned data structure is a list of nested privileges.
     * See Sabre\DAVACL\Plugin::getDefaultSupportedPrivilegeSet for a simple
     * standard structure.
     *
     * If null is returned from this method, the default privilege set is used,
     * which is fine for most common usecases.
     *
     * @return array|null
     */
    public function getSupportedPrivilegeSet()
    {
        return null;
    }
}
