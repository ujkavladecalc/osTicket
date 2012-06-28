<?php
/*********************************************************************
    ajax.upgrader.php

    AJAX interface for Upgrader

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2012 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

if(!defined('INCLUDE_DIR')) die('403');
require_once INCLUDE_DIR.'class.upgrader.php';

class UpgraderAjaxAPI extends AjaxController {

    function upgrade() {
        global $thisstaff, $ost;

        if(!$thisstaff or !$thisstaff->isAdmin() or !$ost)
            Http::response(403, 'Access Denied');

        $upgrader = new Upgrader($ost->getDBSignature(), TABLE_PREFIX, PATCH_DIR);

        //Just report the next action on the first call.
        if(!$_SESSION['ost_upgrader'] || !$_SESSION['ost_upgrader'][$upgrader->getShash()]['progress']) {
            $_SESSION['ost_upgrader'][$upgrader->getShash()]['progress'] = $upgrader->getNextAction();
            Http::response(200, $upgrader->getNextAction());
            exit;
        }

        if($upgrader->isAborted()) {
            Http::response(416, "We have a problem ... wait a sec.");
            exit;
        }

        if($upgrader->getNumPendingTasks()) {
            if($upgrader->doTasks() && !$upgrader->getNumPendingTasks() && $ost->isUpgradePending()) {
                //Just reporting done...with tasks - break in between patches with scripted tasks!
                Http::response(201, "TASKS DONE!");
                exit;
            }
        } elseif($ost->isUpgradePending() && $upgrader->isUpgradable()) {
            $version = $upgrader->getNextVersion();
            if($upgrader->upgrade()) {
                //We're simply reporting progress here - call back will report next action'
                Http::response(200, "Upgraded to $version ... post-upgrade checks!");
                exit;
            }
        } elseif(!$ost->isUpgradePending()) {
            $upgrader->setState('done');
            session_write_close();
            Http::response(201, "DONE!");
            exit;
        }

        if($upgrader->isAborted() || $upgrader->getErrors()) {
            Http::response(416, "We have a problem ... wait a sec.");
            exit;
        }

        Http::response(200, $upgrader->getNextAction());
    }
}
?>
