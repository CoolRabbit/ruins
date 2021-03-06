<?php
/**
 * Support Module
 *
 * Add a Supportlink to every Page
 * @author Markus Schlegel <g42@gmx.net>
 * @copyright Copyright (C) 2008 Markus Schlegel
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Ruins
 */

/**
 * Namespaces
 */
namespace Modules\Support;
use Main\Controller\Page,
    Main\Controller\Link;

/**
 * Support Module
 *
 * Add a Supportlink to every Page
 * @package Ruins
 */
class Support extends \Modules\ModuleBase implements \Common\Interfaces\Module
{
    /**
     * @see Common\Interfaces.Module::getModuleName()
     */
    public function getModuleName() { return "Supportlink Module"; }

    /**
     * @see Common\Interfaces.Module::getModuleDescription()
     */
    public function getModuleDescription() { return "Module to add a Support-Link to each Page"; }

    /**
     * @see Common\Interfaces.Module::prePageGeneration()
     */
    public function prePageGeneration(Page $page)
    {
        $page->nav->add(new Link("Support", "popup=Popup/Support", "shared", "Wenn ein Fehler oder Bug auftritt, bitte hier melden"));
    }
}
?>