<?php

namespace spouts\ics;

use ArrayObject;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

require 'utils/class.iCalReader.php';
require 'utils/owncloud-calendar-export.php';

function repeating_time($rr) {
  if ($rr["FREQ"] === "WEEKLY") {
    $DAYS_OF_WEEK = array("SU" => "Dimanche",
                          "MO" => "Lundi", "TU" => "Mardi",
                          "WE" => "Mercredi", "TH" => "Jeudi",
                          "FR" => "Vendredi", "SA" => "Samedi");
    
    if ($rr["INTERVAL"] === "1") {
      return $DAYS_OF_WEEK[$rr["BYDAY"]]. " prochain";
    }
  }
  return $rr["FREQ"] . "*" . $rr["INTERVAL"];
}

function start_time($dt) {
  $time = strtotime($dt);
  $sdate = date("l jS", $time);
  
  if (!empty(date_parse($dt)["hour"])) {
    $sdate .= ", ". date("H:i", $time);
  }

  return $sdate;
}

function end_time($dt) {
  $time = strtotime($dt);
  if (empty(date_parse($dt)["hour"])) {
    $sdate = date("F jS", $time);
  } else {
    $sdate = date("H:i", $time);
  }

  return $sdate;
}


/**
 * Spout for fetching from ics calendar
 *
 * @package    spouts
 * @subpackage ics
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de) and Kevin Pouget
 * @license    GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de> and Kevin Pouget
 */
class ics extends \spouts\spout {
   /**
     * event iterator
     *
     * @var int
     */
    private $position;
    
    /**
     * name of spout
     *
     * @var string
     */
    public $name = 'ICS Calendar';


    /**
     * description of this source type
     *
     * @var string
     */
    public $description = 'Get your calendar events';

     /**
     * number of days in advance
     *
     * @var int
     */
    public $days = 0;

    /**
     * config params
     * array of arrays with name, type, default value, required, validation type
     *
     * - Values for type: text, password, checkbox, select
     * - Values for validation: alpha, email, numeric, int, alnum, notempty
     *
     * When type is "select", a new entry "values" must be supplied, holding
     * key/value pairs of internal names (key) and displayed labels (value).
     * See /spouts/rss/heise for an example.
     *
     * e.g.
     * array(
     *   "id" => array(
     *     "title"      => "URL",
     *     "type"       => "text",
     *     "default"    => "",
     *     "required"   => true,
     *     "validation" => array("alnum")
     *  ),
     *   ....
     *)
     *
     * @var bool|mixed
     */
    public $params = array(
        "url" => array(
            "title"      => "URL",
            "type"       => "text",
            "default"    => "",
            "required"   => true,
            "validation" => array("notempty")
       ),
        "username" => array(
            "title"      => "Username",
            "type"       => "text",
            "default"    => "",
            "required"   => false,
            "validation" => ""
       ),
        "password" => array(
            "title"      => "Password",
            "type"       => "password",
            "default"    => "",
            "required"   => false,
            "validation" => ""
       ),
        "days" => array(
            "title"      => "Days in advance",
            "type"       => "text",
            "default"    => "-1",
            "required"   => false,
            "validation" => "int"
       )
   );

    /**
     * current fetched items
     *
     * @var array|bool
     */
    protected $items = false;

    /**
     * loads content for given source
     *
     * @return void
     * @param string  $url
     */
    public function load($params) {
      $link = $params['url'];
/*
      if (strpos($link, "owncloud_") === 0) { // owncloud_<cal_id>
        $calendar_lines = owncloud_get_calendar($params['username'],
                                                substr($link, 1+strpos($link, "_")));

        $ical = new ICal(null, explode("\n", $calendar_lines));
      } else {
        if (!empty($params['password']) && !empty($params['username'])) {
          $auth = $params['username'].":".$params['password']."@";
          $link = str_replace("://", "://$auth", $link);
        }

        $ical = new ICal($link);
      }
      
      $this->items = $ical->events();
      
      $this->days = $params['days'];
      $this->rewind();

      $this->params = $params;
*/
    }

    //
    // Iterator Interface
    //

    /**
     * reset iterator
     *
     * @return void
     */
    public function rewind() {
      if ($this->items == false){
        return;
      }
      $this->position = -1;
      $this->next();
    }


    /**
     * receive current item
     *
     * @return SimplePie_Item current item
     */
    public function current() {
      if ($this->items == false) {
        return false;
      }

      return $this;
    }


    /**
     * receive key of current item
     *
     * @return mixed key of current item
     */
    public function key() {
      if ($this->items == false) {
        return false;
      }
      
      return $this->position;
    }


    /**
     * select next item
     *
     * @return SimplePie_Item next item
     */
    public function next() {
      if ($this->items == false) {
        return false;
      }
      while (1) {
        $this->position++;

        $event = $this->current_event();
        if (!$event) {
          return false;
        }
        $event_date = strtotime($event["DTSTART"]);
        $daydiff = round(($event_date - time()) / 60 / 60 / 24, 1); // in days
          
        if (isset($event["RRULE"])) { // repeating event
          if (isset($event["RRULE"]["UNTIL"])
              && strtotime($event["RRULE"]["UNTIL"]) < time())
          {
            // stopped earlier than now
            continue;
          }
          if (isset($event["RRULE"]["COUNT"]))  {
            if ($event["RRULE"]["FREQ"] !== "WEEKLY") {
              echo "Event '".$event["SUMMARY"] . "' not well supported (COUNT and not WEEKLY)<br/>\n";

            } else {
              $length = 7 * intval($event["RRULE"]["INTERVAL"]);
              if ($daydiff + $length < 0) {
                // repeating event until past date
                continue;
              }
            }
          }

          if ($event["RRULE"]["FREQ"] === "WEEKLY") {
            if ($event["RRULE"]["INTERVAL"] !== "1") {
              $event["DESCRIPTION"] = "Not supported yet.";
            }
            $DAYS_OF_WEEK = array("SU" => 0, "MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6);

            if ($daydiff < 0) {
              $daydiff = $DAYS_OF_WEEK[$event["RRULE"]["BYDAY"]] - date("w");
              if ($daydiff < 0) $daydiff += 7;
            }
          } else {
            // ignore for now
            continue;
          }
        } else { // normal event
          if ($event_date < time()) { // not in the past
            continue;
          }
        }
        if ($this->days !== -1 && $daydiff > $this->days) { // not more than $days of distance
          continue;
        }
        $this->items[$this->position]["DDIST"] = $daydiff;

        return $this->current();
      }
    }

    /**
     * end reached
     *
     * @return bool false if end reached
     */
    public function valid() {
      if ($this->items == false) {
        return false;
      }

      return $this->position >= 0 && $this->position < count($this->items);
    }


    /**
     * receive current event
     *
     * @return array current event
     */
    private function current_event() {
      if (!$this->valid()) {
        return false;
      }
      
      return $this->items[$this->position];
    }
    
    /**
     * returns an unique id for this item
     *
     * @return string id as hash
     */
    public function getId() {
      if ($this->items == false || !$this->valid()) {
        return false;
      }
       
      $id = $this->current_event()["UID"];
      $id .= date("Y-m-d"); // refresh the event every day
      if (strlen($id) > 255) {
        $id = md5($id);
      }
      return $id;
    }


    /**
     * returns the current title as string
     *
     * @return string title
     */
    public function getTitle() {
      if ($this->items == false || !$this->valid()) {
        return false;
      }
      
      $event = $this->current_event();
      if (isset($event["RRULE"])) {
        $dispdate = repeating_time($event["RRULE"]) . " " . end_time($event["DTSTART"]);
      } else {
        $dispdate = start_time($event["DTSTART"]);
      }
      $dispdate .= " -> " . end_time($event["DTEND"]);
      
      $text = stripslashes(htmlentities($event["SUMMARY"]));

      return "$dispdate | $text";
    }

    /**
     * returns the global html url for the source
     *
     * @return string title
     */
    public function getHtmlUrl() {
        return $this->$params['url'];
    }


    /**
     * returns the content of this item
     *
     * @return string content
     */
    public function getContent() {
        if ($this->items == false || !$this->valid()) {
          return false;
        }
        
        $event = $this->current_event();
            
        $text = stripslashes(htmlentities($event["SUMMARY"]));
        
        $description = "";
        if (isset($event["DESCRIPTION"])) {
          $description = $event["DESCRIPTION"];
        }
        if (isset($event["LOCATION"])) {
          $description .= "\nLocation: ".htmlentities($event["LOCATION"]);
        }

        if ($event["DDIST"] === 0) {
	  $description .= "\nAujourd'hui";
	} else {
          $description .= "\nDans ".$event["DDIST"]. " jour";
          
          if ($event["DDIST"] > 1) {
            $description .= "s";
          }
        }

        $description = str_replace("\n", "<br/>\n", htmlentities($description));

        return $description;
    }


    /**
     * returns the icon of this item
     *
     * @return string icon url
     */
    public function getIcon() {
        return "None";
    }


    /**
     * returns the link of this item
     *
     * @return string link
     */
    public function getLink() {
        if ($this->items == false || !$this->valid())
          return false;
        
        return "None";
    }
    
    /**
     * returns the date of this item
     *
     * @return string date
     */
    public function getDate() {
      if ($this->items == false || !$this->valid()) {
        return false;
      }

      return date_parse('YmdTHisZ', $this->current_event()["DTSTART"]);
    }


    /**
     * destroy the plugin (prevent memory issues)
     */
    public function destroy() {
        unset($this->items);
        $this->items = false;
    }


    /**
     * returns the xml feed url for the source
     *
     * @return string url as xml
     * @param mixed $params params for the source
     */
    public function getXmlUrl($params) {
        return  "ics://".urlencode($params['url']);
    }
}
