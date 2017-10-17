<?php

namespace Gini\Index;

use
    \Sabre\DAV;
use \Sabre\DAVACL;
use \Sabre\HTTP\URLUtil;

class PrincipalBackend extends DAVACL\PrincipalBackend\AbstractBackend
{

    /**
     * A list of additional fields to support
     *
     * @var array
     */
    protected $fieldMap = [

        /**
         * This property can be used to display the users' real name.
         */
        '{DAV:}displayname' => [
            'field' => 'name',
        ],

        /**
         * This property is actually used by the CardDAV plugin, where it gets
         * mapped to {http://calendarserver.orgi/ns/}me-card.
         *
         * The reason we don't straight-up use that property, is because
         * me-card is defined as a property on the users' addressbook
         * collection.
         */
        '{http://sabredav.org/ns}vcard-url' => [
            'field' => 'vcard-url',
        ],
        /**
         * This is the users' primary email-address.
         */
        '{http://sabredav.org/ns}email-address' =>[
            'field' => 'email',
        ],
    ];

    public function principalPath($principal=null)
    {
        return APP_PATH.'/'.DATA_DIR.'/'.$principal;
    }
    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actualy injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     * @return array
     */
    public function getPrincipalsByPrefix($prefixPath)
    {
        $principals = [];
        foreach (glob($this->principalPath($prefixPath.'/*.yml')) as $fname) {
            $username = basename($fname, '.yml');
            $principal = [
                'uri' => $prefixPath.'/'.$username
            ];

            $row = (array) @yaml_parse_file($fname);
            foreach ($this->fieldMap as $key=>$value) {
                if ($row[$value['field']]) {
                    $principal[$key] = $row[$value['field']];
                }
            }

            $principals[] = $principal;
        }
        return $principals;
    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     * @return array
     */
    public function getPrincipalByPath($path)
    {
        $file = $this->principalPath($path.'.yml');
        if (!file_exists($file)) {
            return;
        }

        $principal = [
            'uri' => $path
        ];

        $row = (array) @yaml_parse_file($file);
        foreach ($this->fieldMap as $key=>$value) {
            if ($row[$value['field']]) {
                $principal[$key] = $row[$value['field']];
            }
        }

        return $principal;
    }

    /**
     * Updates one ore more webdav properties on a principal.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documenation for more info and examples.
     *
     * @param string $path
     * @param \Sabre\DAV\PropPatch $propPatch
     */
    public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch)
    {
    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @param string $test
     * @return array
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        return [];
    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMemberSet($principal)
    {
        return [];
    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMembership($principal)
    {
        return [];
    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     * @param array $members
     * @return void
     */
    public function setGroupMemberSet($principal, array $members)
    {
        throw new DAV\Exception\Forbidden('Not allowed to setGroupMemberSet');
    }
}
