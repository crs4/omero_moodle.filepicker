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
 * Filepicker form element
 *
 * Contains HTML class for a single filepicker form element
 *
 * @package   core_form
 * @copyright 2009 Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;

require_once("HTML/QuickForm/button.php");
require_once($CFG->dirroot . '/repository/lib.php');

require_once($CFG->dirroot . '/lib/form/filepicker.php');

/**
 * Omero Filepicker form element
 *
 * HTML class which extends the core filepicker element (based on button)
 *
 * @package   core_form
 * @category  form
 * @copyright 2015 CRS4
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later // FIXME: to be checked
 */
class MoodleQuickForm_omerofilepicker extends MoodleQuickForm_filepicker
{
    /** @var string html for help button, if empty then no help will icon will be dispalyed. */
    public $_helpbutton = '';

    /** @var array options provided to initalize filemanager */
    // PHP doesn't support 'key' => $value1 | $value2 in class definition
    // We cannot do $_options = array('return_types'=> FILE_INTERNAL | FILE_REFERENCE);
    // So I have to set null here, and do it in constructor
    protected $_options = array('maxbytes' => 0, 'accepted_types' => '*', 'return_types' => null);

    /**
     * Constructor
     *
     * @param string $elementName (optional) name of the filepicker
     * @param string $elementLabel (optional) filepicker label
     * @param array $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     * @param array $options set of options to initalize filepicker
     */
    function MoodleQuickForm_omerofilepicker($elementName = null, $elementLabel = null, $attributes = null, $options = null)
    {
        parent::MoodleQuickForm_filepicker($elementName, $elementLabel, $attributes, $options);
    }

    /**
     * Returns html for help button.
     *
     * @return string html for help button
     */
    function getHelpButton()
    {
        return $this->_helpbutton;
    }

    /**
     * Returns type of filepicker element
     *
     * @return string
     */
    function getElementTemplateType()
    {
        if ($this->_flagFrozen) {
            return 'nodisplay';
        } else {
            return 'default';
        }
    }

    /**
     * Returns HTML for filepicker form element.
     *
     * @return string
     */
    function toHtml()
    {
        global $CFG, $COURSE, $USER, $PAGE, $OUTPUT;

        $endpoint = get_config('omero', 'omero_restendpoint');

        $webgateway_server = substr($endpoint, 0, strpos($endpoint, "/webgateway"));
        $omero_server = "";

        error_log("ENDPOINT: " . $endpoint);
        error_log("GATEWAY ADDR: " . $webgateway_server);

        $id = $this->_attributes['id'];
        $elname = $this->_attributes['name'];

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }
        if (!$draftitemid = (int)$this->getValue()) {
            // no existing area info provided - let's use fresh new draft area
            $draftitemid = file_get_unused_draft_itemid();
            $this->setValue($draftitemid);
        }

        if ($COURSE->id == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($COURSE->id);
        }

        $client_id = uniqid();

        $args = new stdClass();
        // need these three to filter repositories list
        $args->accepted_types = $this->_options['accepted_types'] ? $this->_options['accepted_types'] : '*';
        $args->return_types = $this->_options['return_types'];
        $args->itemid = $draftitemid;
        $args->maxbytes = $this->_options['maxbytes'];
        $args->context = $PAGE->context;
        $args->buttonname = $elname . 'choose';
        $args->elementname = $elname;

        $html = $this->_getTabs();
        $fp = new file_picker($args);
        $options = $fp->options;
        $options->context = $PAGE->context;
        $options->moodle_server = $CFG->wwwroot ;
        $html .= $this->render_file_picker($fp);
        $html .= '<input type="hidden" name="' . $elname . '" id="' . $id .
                 '" value="' . $draftitemid . '" class="filepickerhidden"/>';

        $module = array('name' => 'form_filepicker', 'fullpath' => '/lib/form/omerofilepicker.js',
            'requires' => array('core_filepicker', 'node', 'node-event-simulate', 'core_dndupload'));
        $PAGE->requires->js_init_call('M.form_filepicker.init', array($fp->options), true, $module);

        $nonjsfilepicker = new moodle_url('/repository/draftfiles_manager.php', array(
            'env' => 'filepicker',
            'action' => 'browse',
            'itemid' => $draftitemid,
            'subdirs' => 0,
            'maxbytes' => $options->maxbytes,
            'maxfiles' => 1,
            'ctx_id' => $PAGE->context->id,
            'course' => $PAGE->course->id,
            'sesskey' => sesskey(),
        ));

        // non js file picker
        $html .= '<noscript>';
        $html .= "<div><object type='text/html' data='$nonjsfilepicker' height='160' width='600' style='border:1px solid #000'></object></div>";
        $html .= '</noscript>';

        return $html;
    }


    /**
     * Internal implementation of file picker rendering.
     *
     * @param file_picker $fp
     * @return string
     */
    public function render_file_picker(file_picker $fp)
    {
        global $CFG, $OUTPUT, $USER;
        $options = $fp->options;
        $client_id = $options->client_id;
        $strsaved = get_string('filesaved', 'repository');
        $straddfile = get_string('openpicker', 'repository');
        $strloading = get_string('loading', 'repository');
        $strdndenabled = get_string('dndenabled_inbox', 'moodle');
        $strdroptoupload = get_string('droptoupload', 'moodle');
        $icon_progress = $OUTPUT->pix_icon('i/loading_small', $strloading) . '';

        $currentfile = $options->currentfile;
        if (empty($currentfile)) {
            $currentfile = '';
        } else {
            $currentfile .= ' - ';
        }
        if ($options->maxbytes) {
            $size = $options->maxbytes;
        } else {
            $size = get_max_upload_file_size();
        }
        if ($size == -1) {
            $maxsize = '';
        } else {
            $maxsize = get_string('maxfilesize', 'moodle', display_size($size));
        }
        if ($options->buttonname) {
            $buttonname = ' name="' . $options->buttonname . '"';
        } else {
            $buttonname = '';
        }
        $html = <<<EOD
            <!-- if no URL has been selected yet -->
            <div class="filemanager-loading mdl-align" id='filepicker-loading-{$client_id}' style="border: none;">
                $icon_progress
            </div>
            <div id="filepicker-wrapper-{$client_id}" class="mdl-left" style="display:none">
            <div>
                <input type="button" class="fp-btn-choose" id="filepicker-button-{$client_id}" value="{$straddfile}"{$buttonname}/>
                <span> $maxsize </span>
            </div>

            <input type="button" value="prova" onclick="setIframeHeight('omeroviewport');" />
EOD;
        if ($options->env != 'url') {
            $html .= <<<EOD
            <!-- if a URL has been selected -->
            <div id="file_info_{$client_id}" class="mdl-left filepicker-filelist" style="border: none; position: relative;">
                <div class="filepicker-filename" style="border: none;">
                    <div class="filepicker-container">$currentfile
                        <div class="dndupload-message">$strdndenabled <br/>
                            <div class="dndupload-arrow"></div>
                        </div>
                    </div>
                    <div class="dndupload-progressbars"></div>
                </div>
                <div>
                    <div class="dndupload-target">{$strdroptoupload}<br/>
                        <div class="dndupload-arrow"></div>
                    </div>
                </div>
            </div>
EOD;
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * export uploaded file
     *
     * @param array $submitValues values submitted.
     * @param bool $assoc specifies if returned array is associative
     * @return array
     */
    function exportValue(&$submitValues, $assoc = false)
    {
        global $USER;

        $draftitemid = $this->_findValue($submitValues);
        if (null === $draftitemid) {
            $draftitemid = $this->getValue();
        }

        // make sure max one file is present and it is not too big
        if (!is_null($draftitemid)) {
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false)) {
                $file = array_shift($files);
                if ($this->_options['maxbytes']
                    and $this->_options['maxbytes'] !== USER_CAN_IGNORE_FILE_SIZE_LIMITS
                    and $file->get_filesize() > $this->_options['maxbytes']
                ) {

                    // bad luck, somebody tries to sneak in oversized file
                    $file->delete();
                }
                foreach ($files as $file) {
                    // only one file expected
                    $file->delete();
                }
            }
        }

        return $this->_prepareValue($draftitemid, true);
    }
}
