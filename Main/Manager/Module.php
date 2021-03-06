<?php
/**
 * Modulesystem Class
 *
 * Class to manage Modules
 * @author Markus Schlegel <g42@gmx.net>
 * @copyright Copyright (C) 2008 Markus Schlegel
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Ruins
 */

/**
 * Namespaces
 */
namespace Main\Manager;
use Main\Entities;

/**
 * Global Includes
 */
require_once(DIR_INCLUDES."includes.inc.php");

/**
 * Modulesystem Class
 *
 * Class to manage Modules
 * @package Ruins
 */
class Module
{
    /**
     * Class Constants
     */
    const EVENT_PRE_PAGEHEADER      = "prePageHeader";
    const EVENT_PRE_PAGECONTENT     = "prePageContent";
    const EVENT_PRE_PAGEGENERATION  = "prePageGeneration";
    const EVENT_POST_PAGEGENERATION = "postPageGeneration";

    /**
     * Call Module
     * @param string $functionname Name of the Module-Event to call
     * @param object $object Optional Object
     */
    public static function callModule($eventname, $object=NULL)
    {
       foreach(self::getModuleListFromDatabase(true) as $module) {
            $classname = $module->classname;
            $classname::$eventname($object);
        }
    }

    /**
     * Get the List of Modules from the Filesystem
     * @return array List of Modulenames
     */
    public static function getModuleListFromFilesystem()
    {
        $result = array();
        $dircontent = System::getDirList(DIR_MODULES);

        foreach($dircontent['directories'] as $dirname) {
            // Generate the Classname of the Module-Init-File
            $classname = "Modules\\".$dirname."\\".$dirname;
            if (self::validateModule($classname)) {
                $result[] = $classname;
            }
        }

        return $result;
    }

    /**
     * Validate given Module-Class
     * @param string $initClass
     * @return bool true if Class is valid, else false
     */
    public static function validateModule($initClass)
    {
        if (class_exists($initClass, true)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the List of Modules from the Database
     * @param string $moduletype Moduletype
     * @return array List of Modules (all Properties)
     */
    public static function getModuleListFromDatabase($onlyenabled=false)
    {
        $qb = getQueryBuilder();

        $qb ->select("module")
            ->from("Main:Module", "module");
        if ($onlyenabled) $qb->where("module.enabled = ?1")->setParameter(1, true);

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Clear ModuleList from Database
     */
    public static function clearModuleList()
    {
        $qb = getQueryBuilder();

        $qb ->delete("Main:Module")
            ->getQuery()->execute();
    }

    /**
     * Synchronize the Modulelist existing at the Database with the Modulelist existing in our Directory
     * @return bool true if successful, else false
     */
    public function syncModuleListToDatabase()
    {
        global $em;

        $addFlag        = true;
        $moduleFSList	= self::getModuleListFromFilesystem();
        $moduleDBList	= self::getModuleListFromDatabase();

        foreach($moduleFSList as $moduleFS) {
            foreach($moduleDBList as $moduleDB) {
                if ($moduleDB->classname == $moduleFS) {
                    $addFlag = false;
                }
            }

            if ($addFlag) {
                // execute init()-Method of unknown Module
                call_user_func($moduleFS."::init");
            }
        }
        $em->flush();

        return true;
    }
}
?>