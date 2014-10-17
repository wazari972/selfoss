<?php
/**
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 * https://github.com/georgehrke/cl-calendarimport/blob/master/automatedimport.php
 */

define('OCROOT', '/var/www/alternc/k/kevin/www/0x972.info/owncloud/');

function owncloud_get_calendar($username, $cal_id) {
  //it's not necessary to load all apps
  $RUNTIME_NOAPPS = true;
  require_once(OCROOT . '/lib/base.php');
  require_once(OCROOT . '/apps/calendar/appinfo/app.php');
  
  //set userid
  OC_User::setUserId($username);
  
  OCP\User::checkLoggedIn();
  OCP\App::checkAppEnabled('calendar');
  
  $calendar = OC_Calendar_App::getCalendar($cal_id, true, true);
  if(!$calendar) {
    return;
  }
  
  return OC_Calendar_Export::export($cal_id, OC_Calendar_Export::CALENDAR);
}
