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
 * Site event reminder handler.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');

/**
 * Class to specify the reminder message object for site (global) events.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_reminder extends local_reminder {

    /**
     * Generates a message content as a HTML for site email.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @param stdClass $ctxinfo additional context info needed to process.
     * @return string Message content as HTML text.
     */
    public function get_message_html($user=null, $changetype=null, $ctxinfo=null) {
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
        $description = $this->event->description;
        $output['descriptionscourse'] = $this->write_description($description, $this->event);
        $output = array_merge($this->get_html_footer(), $output);

        return render_from_template('local_reminders\site_reminder', $output);
    }

    /**
     * Generates a message content as a plain-text for site wide noty.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @return string Message content as plain-text.
     */
    public function get_message_plaintext($user=null, $changetype=null) {
        $text  = $this->get_message_title().' ['.$this->pluralize($this->aheaddays, ' day').' to go]'."\n";
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->get_tzinfo_plain($user, $this->event)."\n";
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description."\n";

        return $text;
    }

    /**
     * Returns 'reminders_site' name.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'reminders_site';
    }

    /**
     * Generates a message title for the site reminder.
     *
     * @param string $type type of message to be send (null=reminder cron)
     * @return string Message title as a plain-text.
     */
    public function get_message_title($type=null) {
        return $this->event->name;
    }
}
