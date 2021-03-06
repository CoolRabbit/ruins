<?php
/**
 * Battleround getter
 *
 * Retrieves the Battleround for the given Char
 * @author Markus Schlegel <g42@gmx.net>
 * @copyright Copyright (C) 2007 Markus Schlegel
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version SVN: $Id: battle_checknewround.ajax.php 326 2011-04-19 20:19:34Z tacki $
 * @package Ruins
 */

/**
 * Global Includes
 */
require_once("../../../config/dirconf.cfg.php");
require_once(DIR_INCLUDES."includes.inc.php");

$characterid = rawurldecode($_GET['characterid']);

if (isset($characterid) && is_numeric($characterid)) {

    $qb = getQueryBuilder();

    $result = $qb   ->select("bt.round")
                    ->from("Main:Battle", "bt")
                    ->from("Main:BattleMember", "bm")
                    ->where("bm.battle = bt")
                    ->andWhere("bm.character = ?1")->setParameter(1, $characterid)
                    ->getQuery()->getOneOrNullResult();

    echo json_encode((int)$result);
}

?>
