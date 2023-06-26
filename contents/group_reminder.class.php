<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Group event reminder handler.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->libdir . '/accesslib.php');

/**
 * Class to specify the reminder message object for group events.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_reminder extends local_reminder {

    /**
     * group reference.
     *
     * @var object
     */
    private $group;
    /**
     * course reference.
     *
     * @var object
     */
    private $course;
    /**
     * course module context reference.
     *
     * @var object
     */
    private $cm;

    /**
     * activity reference.
     *
     * @var object
     */
    private $activityobj;
    /**
     * module name.
     *
     * @var string
     */
    private $modname;

    /**
     * Creates a new group event instance.
     *
     * @param object $event calendar event.
     * @param object $group group instance.
     * @param integer $aheaddays number of days ahead.
     */
    public function __construct($event, $group, $aheaddays = 1) {
        parent::__construct($event, $aheaddays);
        $this->group = $group;
        $this->load_course_object();
    }

    /**
     * Cleanup this reminder instance.
     */
    public function cleanup() {
        parent::cleanup();

        if (isset($this->activityobj)) {
            unset($this->activityobj);
        }
    }

    /**
     * Set activity instance if there is any.
     *
     * @param string $modulename module name.
     * @param object $activity activity instance
     */
    public function set_activity($modulename, $activity) {
        $this->activityobj = $activity;
        $this->modname = $modulename;
    }

    /**
     * Loads course reference using provided group reference.
     *
     * @return void.
     */
    private function load_course_object() {
        global $DB;

        $this->course = $DB->get_record('course', array('id' => $this->group->courseid));
        if (!empty($this->course) && !empty($this->event->instance)) {
            $cmx = get_coursemodule_from_instance($this->event->modulename, $this->event->instance, $this->group->courseid);
            if (!empty($cmx)) {
                $this->cm = get_context_instance(CONTEXT_MODULE, $cmx->id);
            }
        }
    }

    /**
     * Generates a message content as a HTML for group reminder.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @param stdClass $ctxinfo additional context info needed to process.
     * @return string Message content as HTML text.
     */
    public function get_message_html($user=null, $changetype=null, $ctxinfo=null) {
        global $CFG, $OUTPUT;

        $output = $this->get_reminder_header();
        $output['tbodycssstyle'] = $this->tbodycssstyle;
        $contenttitle = $this->get_message_title();

        if (!isemptystring($changetype)) {
            $titleprefixlangstr = get_string('calendarevent'.strtolower($changetype).'prefix', 'local_reminders');
            $contenttitle = "[$titleprefixlangstr]: $contenttitle";
        }

        $output['generate_event_link'] = $this->generate_event_link();
        $output['titlestyle'] = $this->titlestyle;
        $output['contenttitle'] = $contenttitle;
        $output['time'] = format_event_time_duration($user, $this->event);
        $output['location'] = $this->write_location_info($this->event);

        if (!empty($this->course)) {
            $output['ifnotemptycourse'] = true;
            $output['fullname'] = $this->course->fullname;
        } else {
            $output['ifnotemptycourse'] = false;
        }

        if (!empty($this->cm)) {
            $output['ifnotemptycm'] = true;
            $output['cmurl'] = $this->cm->get_url();
            $output['cmurlname'] = $this->cm->get_context_name();
        } else {
            $output['ifnotemptycm'] = false;
        }

        if (isset($CFG->local_reminders_groupshowname) && $CFG->local_reminders_groupshowname) {
            $output['ifissetgroupname'] = true;
            $output['groupname'] = $this->group->name;
        } else {
            $output['ifissetgroupname'] = false;
        }

        $formattercls = null;
        $appendinfo = '';

        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $formattercls = new $clsname;

                $formattercls->append_info($appendinfo, $this->modname, $this->activityobj, $user, $this->event);
                $output['appendinfo'] = $appendinfo;
            }
        }

        $description = isset($formattercls) ? $formattercls->get_description($this->activityobj, $this->event) :
            $this->event->description;
        $output['descriptionscourse'] = $this->write_description($description, $this->event);
        $output = array_merge($this->get_html_footer(), $output);

        return render_from_template('local_reminders\group_reminder', $output);
    }

    /**
     * Generates a message content as a plain-text for group reminder.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @return string Message content as plain-text.
     */
    public function get_message_plaintext($user=null, $changetype=null) {
        $text  = $this->get_message_title().' ['.$this->pluralize($this->aheaddays, ' day').' to go]'."\n";
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->get_tzinfo_plain($user, $this->event)."\n";
        if (!empty($this->course)) {
            $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname."\n";
        }
        if (!empty($this->cm)) {
            $text .= get_string('contenttypeactivity', 'local_reminders').': '.$this->cm->get_context_name()."\n";
        }
        $text .= get_string('contenttypegroup', 'local_reminders').': '.$this->group->name."\n";
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description."\n";

        return $text;
    }

    /**
     * Returns 'reminders_group' name.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'reminders_group';
    }

    /**
     * Generates a message title for the group reminder.
     *
     * @param string $type type of message to be send (null=reminder cron)
     * @return string Message title as a plain-text.
     */
    public function get_message_title($type=null) {
        $title = '';
        if (!empty($this->course)) {
            $title .= '('.$this->course->shortname;
            if (!empty($this->cm)) {
                $title .= '-'.get_string('modulename', $this->event->modulename);
            }
            $title .= ') ';
        }
        $title .= $this->event->name;
        return $title;
    }

    /**
     * Adds group id and activity id (if exists) to header.
     *
     * @return array additional headers.
     */
    public function get_custom_headers() {
        $headers = parent::get_custom_headers();

        $headers[] = 'X-Group-Id: '.$this->group->id;
        if (!empty($this->cm)) {
            $headers[] = 'X-Activity-Id: '.$this->cm->id;
        }
        return $headers;
    }
}
