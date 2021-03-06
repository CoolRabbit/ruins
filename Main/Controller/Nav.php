<?php
/**
 * Nav Class
 *
 * Navigation Class which cares about the Navigation between the Pages
 * @author Markus Schlegel <g42@gmx.net>
 * @copyright Copyright (C) 2007 Markus Schlegel
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Ruins
 */

/**
 * Namespaces
 */
namespace Main\Controller;
use Main\Entities,
    Common\Controller\Error,
    Common\Controller\BaseObject,
    Main\Manager,
    Common\Interfaces\OutputObject;

/**
 * Nav Class
 *
 * Navigation Class which cares about the Navigation between the Pages
 * @package Ruins
 */
class Nav
{
    /**
     * Flag to save Navigation to cache
     * @var bool
     */
    public $cacheNavigation;

    /**
     * Character Object
     * @var Entities\Character
     */
    private $_char;

    /**
     * Output Object
     * @var OutputObject
     */
    private $_outputObject;

    /**
     * Nav Validation Enable Flag
     * @var bool
     */
    private $_validationEnabled;

    /**
     * Allowed Navigation List
     * @var array
     */
    private $_linkList;

    /**
     * Last Navigation added status
     * @var bool
     */
    private $_lastNavAddedStatus;

    /**
     * constructor - load the default values and initialize the attributes
     * @param Entities\Character $char Character Object
     * @param OutputObject $outputobject Parent Outputobject
     */
    function __construct($character=false, $outputobject=false)
    {
        // Attribute Init
        $this->cacheNavigation = false;
        $this->_char = $character;
        $this->_linkList = array();
        $this->_lastNavAddedStatus = false;

        // Default to enabled Validation
        if ($this->_char === false) {
            $this->disableValidation();
        } else {
            $this->enableValidation();
        }

        if ($outputobject instanceof OutputObject) {
            $this->_outputObject = $outputobject;
        } else {
            $this->_outputObject = false;
        }
    }

    /**
     * Add Navigation Head
     * @param string $title Title
     * @param string $linkcontainer Name of the Container this Link is shown in
     * @param Entities\Group $restriction Group the Link is restricted to
     * @return Nav This Object
     */
    public function addHead($title, $linkcontainer="main", Entities\Group $restriction=NULL)
    {
        $link = new Link($title, false, $linkcontainer);

        if ($restriction) $link->setRestriction($restriction);

        $this->add($link);
        return $this;
    }


    /**
     * Add Navigation Link
     * @param string $name Shown Linkname
     * @param string $url URL
     * @param string $linkcontainer Name of the Container this Link is shown in
     * @param Entities\Group $restriction Group the Link is restricted to
     * @return Nav This Object
     */
    public function addLink($name, $url, $linkcontainer="main", Entities\Group $restriction=NULL)
    {
        $link = new Link($name, $url, $linkcontainer);

        if ($restriction) $link->setRestriction($restriction);

        $this->add($link);
        return $this;
    }

    /**
     * Add a hidden Link to allow HTML-Forms in protected Areas
     * @param string $url URL
     * @param Entities\Group $restriction Group the Link is restricted to
     * @return Nav This Object
     */
    public function addHiddenLink($url, Entities\Group $restriction=NULL)
    {
        $link = new Link(false, $url);

        if ($restriction) $link->setRestriction($restriction);

        $this->add($link);
        return $this;
    }

    /**
     * Add Navigation Link inside a Text
     * @param string $text Shown linked Text
     * @param string $url URL
     * @param Entities\Group $restriction Group the Link is restricted to
     * @return Nav This Object
     */
    public function addTextLink($text, $url, Entities\Group $restriction=NULL)
    {
        // Add Hidden Link
        $this->addHiddenLink($url, $restriction);

        // Output Link
        if ($this->_lastNavAddedStatus === true) {
            if ($this->_outputObject) {
                $this->_outputObject->output("<a href='?". $url . "'>" . $text . "</a>", true);
            } else {
                throw new Error("\$this->_outputObject is not usable here, because it's not an instance of OutputObject!");
            }
        }

        return $this;
    }

    /**
     * Add a Link to the Linklist
     * @param Link $link Linkobject to add
     * @param int $linklistid Absolute Position of the Link
     * @return bool true if successful, else false
     */
    public function add(Link $link, $linklistid=0)
    {
        // Check if the Link is valid
        if ($this->validationEnabled() && $link->url) {
            if (!Manager\System::validatePHPFilePath($link->url)) {
                $this->_lastNavAddedStatus = false;
                return false;
            }
        }

        // Check if Link already exists
        if ($this->_exists($link->displayname, $link->url)) {
            $this->_lastNavAddedStatus = true;
            return true;
        }

        if (!$this->validationEnabled() || $link->isAllowedBy($this->_char) ) {

            $linkdescription = array(	"displayname"=>$link->displayname,
                                        "url"=>$link->url,
                                        "position"=>$link->position,
                                        "description"=>$link->description
                                    );

            if ($linklistid > 0) {
                // insert the nav at the given position
                array_splice($this->_linkList, $linklistid-1, 0, array($linkdescription));
            } else {
                // add the nav to the end of the array
                $this->_linkList[] = $linkdescription;
            }
            $this->_lastNavAddedStatus = true;
            return true;
        } else {
            $this->_lastNavAddedStatus = false;
            return false;
        }
    }

    /**
     * Remove a Link from the Linklist
     * @param string $entry Displayname or URL of the Link to remove
     */
    public function remove($entry)
    {
        // Run through the Properties...
        foreach ($this->_linkList as $linkarray) {
            if ($linkarray['displayname'] == $entry || $linkarray['url'] == $entry) {
                unset ($this->_linkList[$displayname]);
            }
        }
    }

    /**
     * Load the Characters allowed Navigation
     */
    public function load()
    {
        if ($this->_char === false) {
            // public navigation
            $this->_linkList = array();
        } elseif (is_array($this->_char->allowednavs)) {
            // existing private navigation
            $this->_linkList = $this->_char->allowednavs;
        } else {
            // new private navigation
            $this->_linkList = array();
        }
    }

    /**
     * Load the Characters allowed Navigation from Cache
     */
    public function loadFromCache()
    {
        // existing private navigation from cache
        $this->_linkList = $this->_char->allowednavs_cache;

        // Disable cacheNavigation for the Rest of this Page
        // and save
        $this->cacheNavigation = false;
        $this->save();
    }

    /**
     * Save the Characters allowed Navigation
     */
    public function save()
    {
        if ($this->_char !== false) {

            // Only save if this Navigation is private
            $this->_char->allowednavs = $this->_linkList;

            if ($this->cacheNavigation) {
                $this->_char->allowednavs_cache = $this->_linkList;
            }
        }
    }

    /**
     * Return Linklist
     */
    public function getLinkList()
    {
        return $this->_linkList;
    }

    /**
     * Clear Linklist
     */
    public function clear()
    {
        $this->_linkList = array();
    }

    /**
     * Return Request URL
     * @return string The Request URL
     */
    public function getRequestURL()
    {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], "?") !== false) {
            return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        } else {
            return false;
        }
    }

    /**
     * Return Referer URL
     * @var string The Referer URL
     */
    public function getRefererURL()
    {
        if (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] != "") {
            return $_SERVER['HTTP_REFERER'];
        } else {
            return false;
        }
    }

    /**
     * Check if the Link is valid
     * @param string $url URL to check
     * @param bool $noclear Don't clear, just check
     * @return bool true if successful, else false
     */
    public function checkRequestURL($url=false, $noclear=false)
    {
        global $user;

        if (!$url) {
            $url = $this->getRequestURL();
        }

        if ($this->_exists(false, $url)) {
            // Add DebugLogEntry
            $user->addDebugLog("Open $url", "verbose");

            if (!$noclear) {
                // URL is valid, so we clear the old linklist
                $this->clear();
            }

            return $url;
        } else {
            return false;
        }
    }

    /**
     * Redirect to another page
     * @param string $url Target of the redirect
     */
    public function redirect($url)
    {
        global $em;

        // Add Link to Navigation
        $this->add(new Link("Redirection", $url));

        // Write current Navigation to Characters allowedNavs
        $this->save();

        // Flush EntityManager
        $em->flush();

        // Check Transactions
        $database = getDBInstance();
        if ($database->isTransactionActive()) {
            // Commit Database-Changes
            $database->commit();
        }

        // Redirect
        $baseurl = Manager\System::htmlpath(DIR_BASE);
        if (isset($config) && $config->get("useManualRedirect", 0)) {
            echo "Forward to $url <br />";
            echo "<a href='$baseurl?" . $url ."'>Continue</a>";
            exit;
        } else {
            $header = "Location: $baseurl?" . $url;
            header($header);
            exit;
        }
    }

    /**
     * Enable Nav Validation
     */
    public function enableValidation()
    {
        $this->_validationEnabled = true;
    }

    /**
     * Disable Nav Validation
     */
    public function disableValidation()
    {
        $this->_validationEnabled = false;
    }

    /**
     * Check if Nav Validation is enabled
     * @return bool true if Validation is enabled, else false
     */
    public function validationEnabled()
    {
        return $this->_validationEnabled;
    }

    /**
     * Check if the Link is valid
     * @access private
     * @param string $url URL to check
     * @return bool true if valid, else false
     */
    private function _exists($displayname=false, $url=false)
    {
        // Run through the Properties...
        foreach ($this->_linkList as $linkarray) {
            if ($displayname && $url) {
                //echo "{$displayname} == {$linkarray['displayname']} && {$url} == {$linkarray['url']} ...";
                if ($displayname == $linkarray['displayname'] && $url == $linkarray['url']) {
                    //echo "<font color='green'>ok</font><br />";
                    return true;
                } else {
                    //echo "<font color='red'>not ok</font><br />";
                }
            } elseif ($displayname) {
                //echo "{$displayname} == {$linkarray['displayname']} ...";
                if ($displayname == $linkarray['displayname']) {
                    //echo "<font color='green'>ok</font><br />";
                    return true;
                } else {
                    //echo "<font color='red'>not ok</font><br />";
                }
            } elseif ($url) {
                //echo "{$url} == {$linkarray['url']} ...";
                if ($url == $linkarray['url']) {
                    //echo "<font color='green'>ok</font><br />";
                    return true;
                } else {
                    //echo "<font color='red'>not ok</font><br />";
                }
            } else {
                //echo "<font color='red'>not ok</font><br />";
                return false;
            }
        }

        return false;
    }
}
?>
