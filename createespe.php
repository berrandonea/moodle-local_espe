<title>
Enregistrement des étudiants et professeurs et création des cours de l'ESPE
</title>

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Vérifier que le mec est connecté et à le droit de lancer le script.

require_once('../../config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');
require_once($CFG->libdir .'/coursecatlib.php');
require_once($CFG->libdir .'/enrollib.php');

$courseid = required_param('courseid', PARAM_INT);

$contextcourseid = $DB->get_record('context', array('contextlevel' => 50, 'instanceid' => $courseid))->id;
$parentcategoryid = $DB->get_record('course', array('id' => $courseid))->category;

if ($DB->record_exists('role_assignments', array('roleid' => 3, 'contextid' => $contextcourseid,'userid' => $USER->id))) {

    echo $OUTPUT->header();

    echo "Création des comptes étudiants...<br>";
    local_create_students();
    echo "Comptes étudiants créés<br>";
    echo "Création des comptes professeurs...<br>";
    local_create_teachers();
    echo "Comptes professeurs créés<br>";
    echo "Création des cours...<br>";
    local_create_courses($parentcategoryid);
    echo "Cours créés<br>";
    $returnstring = new moodle_url('../../course/index.php', array('categoryid' => $parentcategoryid));
    echo "<a href =$returnstring >Retour vers la catégorie</a><br>";

    echo $OUTPUT->footer();
}
function local_create_students() {

    global $CFG, $DB;

    $fichiercsv = fopen($CFG->dirroot.'/local/espe/data/students.csv', 'r');

    $row = 1;
    if ($fichiercsv != FALSE) {

        while (($data = fgetcsv($fichiercsv, 200, ";")) != FALSE) {

            if ($row != 1) {

                // On regarde si un utilisateur a déjà ce mail.
                $sql = "SELECT id FROM mdl_user WHERE email LIKE '".$data[3]."'";
                $previoususer = $DB->record_exists_sql($sql);


                if (!$previoususer) {

                    $username = utf8_encode(strtolower($data[6]));

                    if ($DB->record_exists('user', array('username' => $username))) {

                        $sqlmaxid = "SELECT MAX(id) FROM mdl_user";

                        $idnumber = $DB->get_record_sql($sqlmaxid) + 1;

                        $username = $username.$idnumber;
                    }

                    // Inscription dans la base de données.
                    $user->auth = 'manual';
                    $user->confirmed = 1;
                    $user->mnethostid = 1;
                    $user->email = utf8_encode($data[3]);
                    $user->username = $username;
                    $user->lastname = utf8_encode(ucwords(strtolower($data[1])));
                    $user->firstname = utf8_encode(ucwords(strtolower($data[2])));
                    $user->lang = 'fr';
                    $user->timecreated = time();
                    $user->timemodified = time();

                    $user->id = $DB->insert_record('user', $user);

                        setnew_password_and_mail($user);
                }
            }

            $row++;
        }
    }

    fclose($fichiercsv);
}

function local_create_teachers() {

    global $DB, $CFG;

    // Ouverture du fichier CSV.

    $fichiercsv = fopen($CFG->dirroot.'/local/espe/data/teachers.csv', 'r');

    $row = 1;
    if ($fichiercsv != FALSE) {

        while (($data = fgetcsv($fichiercsv, 200, ";")) != FALSE) {

            if ($row != 1) {

                // On regarde si un utilisateur a déjà ce mail.
                $sql = "SELECT id FROM mdl_user WHERE email LIKE '".$data[3]."'";
                $previoususer = $DB->record_exists_sql($sql);


                if (!$previoususer) {

                    $username = utf8_encode(strtolower($data[6]));

                    if ($DB->record_exists('user', array('username' => $username))) {

                        $sqlmaxid = "SELECT MAX(id) FROM mdl_user";

                        $idnumber = $DB->get_record_sql($sqlmaxid) + 1;

                        $username = $username.$idnumber;
                    }

                    // Inscription dans la base de données.
                    $user->auth = 'manual';
                    $user->confirmed = 1;
                    $user->mnethostid = 1;
                    $user->email = utf8_encode($data[3]);
                    $user->username = $username;
                    $user->lastname = utf8_encode(ucwords(strtolower($data[1])));
                    $user->firstname = utf8_encode(ucwords(strtolower($data[2])));
                    $user->lang = 'fr';
                    $user->timecreated = time();
                    $user->timemodified = time();

                    $user->id = $DB->insert_record('user', $user);

                    setnew_password_and_mail($user);

                    role_assign(2, $user->id, 1, '', 0, time());

                    $scormfav->userid = $user->id;
                    $scormfav->listname = 'activities';
                    $scormfav->elementname = 'scorm';
                    $DB->insert_record('block_catalogue_fav', $scormfav);
                    
                    $visiofav->userid = $user->id;
                    $visiofav->listname = 'activities';
                    $visiofav->elementname = 'bigbluebuttonbn';
                    $DB->insert_record('block_catalogue_fav', $visiofav);
                }
            }

            $row++;
        }
    }

    fclose($fichiercsv);
}

function local_create_courses($parentcategoryid) {

    global $DB, $CFG;

    // Ouverture du fichier CSV.

    $fichiercsv = fopen($CFG->dirroot.'/local/espe/data/structure.csv', 'r');

    $row = 1;
    if ($fichiercsv != FALSE) {

        while (($data = fgetcsv($fichiercsv, 300, ";")) != FALSE) {

            if ($row != 1) {

                if ($data[0] != "") {

                    if (!$DB->record_exists('course_categories',
                            array('name' => utf8_encode($data[0]), 'parent' => $parentcategoryid))) {

                        $category = new stdClass();
                        $category->name = utf8_encode($data[0]);
                        $category->parent = $parentcategoryid;
                        // create category
                        // get category id

                        $yearid = \coursecat::create($category)->id;
                    } else {

                        $yearid = $DB->get_record('course_categories',
                                array('name' => utf8_encode($data[0]), 'parent' => $parentcategoryid))->id;
                    }
                }

                if ($data[1] != "" && isset($yearid)) {

                    if (!$DB->record_exists('course_categories',
                            array('name' => utf8_encode($data[1]), 'parent' => $yearid))) {

                        $category = new stdClass();
                        $category->name = utf8_encode($data[1]);
                        $category->parent = $yearid;
                        // create category
                        // get category id
                        $greatcategoryid = \coursecat::create($category)->id;
                    } else {

                        $greatcategoryid = $DB->get_record('course_categories',
                                array('name' => utf8_encode($data[1]), 'parent' => $yearid))->id;
                    }
                }

                if ($data[2] != "" && isset($greatcategoryid)) {

                    if (!$DB->record_exists('course_categories',
                            array('name' => utf8_encode($data[2]), 'parent' => $greatcategoryid))) {

                        $category = new stdClass();
                        $category->name = utf8_encode($data[2]);
                        $category->parent = $greatcategoryid;
                        // create category
                        // get category id

                        $categoryid = \coursecat::create($category)->id;
                    } else {

                        $categoryid = $DB->get_record('course_categories',
                                array('name' => utf8_encode($data[2]), 'parent' => $greatcategoryid))->id;
                    }
                }

                if ($data[3] != "" && isset($categoryid)) {

                    if (!$DB->record_exists('course_categories',
                            array('name' => utf8_encode($data[3]), 'parent' => $categoryid))) {

                        $category = new stdClass();
                        $category->name = utf8_encode($data[3]);
                        $category->parent = $categoryid;
                        // create category
                        // get category id

                        $subcategoryid = \coursecat::create($category)->id;
                    } else {

                        $subcategoryid = $DB->get_record('course_categories',
                                array('name' => utf8_encode($data[3]), 'parent' => $categoryid))->id;
                    }
                }

                if (($data[4] != "" || $data[5] != "" || $data[6] != "" ||$data[7] != "")
                        && isset($subcategoryid)) {

                    $subcategoryname = $DB->get_record('course_categories', array('id' => $subcategoryid))->name;

                    if ($data[4] != "") {

                        $generatedname = $subcategoryname." Groupe ".utf8_encode($data[4]);
                    } else if ($data[5] != "") {

                        $generatedname = $subcategoryname." ".utf8_encode($data[5]);
                    } else if ($data[6] != "") {

                        $generatedname = $subcategoryname." ".utf8_encode($data[6]);
                    } else if ($data[7] != "") {

                        $generatedname = $subcategoryname." ".utf8_encode($data[7]);
                    }           

                    if (!$DB->record_exists('course',
                            array('category' => $subcategoryid, 'fullname' => $generatedname))) {

                        $course = new stdClass();
                        $course->category = $subcategoryid;
                        $course->fullname = $generatedname;
                        $course->shortname = $generatedname;

                        if ($DB->record_exists('course', array('shortname' => $generatedname))) {

                            $course->shortname = $generatedname.$subcategoryid;
                        }

                        $newcourse = create_course($course);

                        $courseid = $newcourse->id;

                        $idnumber = "Y2017-AEAD".$courseid;

                        $DB->set_field('course', 'idnumber', $idnumber, array('id' => $courseid));

                    } else {

                        $newcourse = $DB->get_record('course',
                                array('category' => $subcategoryid, 'fullname' => $generatedname));

                        $courseid = $newcourse->id;
                    }

                    $listgroups = explode('/', $data[4]);

                    foreach ($listgroups as $group) {

                        $fichiercsvstudents = fopen($CFG->dirroot.'/local/espe/data/students.csv', 'r');

                        $rowstudents = 1;
                        if ($fichiercsvstudents != FALSE) {

                            while (($datastudents = fgetcsv($fichiercsvstudents, 200, ";")) != FALSE) {

                                if ($rowstudents != 1) {

                                    $yearname = $DB->get_record('course_categories', array('id' => $yearid))->name;

                                    if ($datastudents[7] == $group && $datastudents[4] == $yearname) {

                                        $student = $DB->get_record('user', array('email' => $datastudents[3]));
                                        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

                                        local_enrol_user($newcourse, $student->id, $studentrole->id);
                                    }
                                }
                                $rowstudents++;
                            }
                        }
                        fclose($fichiercsvstudents);
                    }

                    $fichiercsvteachers = fopen($CFG->dirroot.'/local/espe/data/teachers.csv', 'r');

                    $rowteachers = 1;
                    if ($fichiercsvteachers != FALSE) {

                        while (($datateachers = fgetcsv($fichiercsvteachers, 200, ";")) != FALSE) {

                            if ($rowteachers != 1) {

                                if ($datateachers[6] == $data[5] ||
                                        $datateachers[6] == $data[6] || $datateachers[6] == $data[7]) {

                                    $teacher = $DB->get_record('user', array('email' => $datateachers[3]));
                                    $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));

                                    local_enrol_user($newcourse, $teacher->id, $teacherrole->id);
                                }
                            }
                            $rowteachers++;
                        }
                    }

                    fclose($fichiercsvteachers);

                    // Insertion des modules

                    if (!$DB->record_exists('chat', array('course' => $courseid, 'name' => "Chat"))) {

                        $chat = new stdClass();
                        $chat->course = $courseid;
                        $chat->name = "Chat";
                        $chat->intro = "Chat du cours ".$newcourse->shortname;
                        $chat->introformat = 1;
                        $chat->timemodified = time();
                        $chat->id = $DB->insert_record('chat', $chat);

                        local_create_module($courseid, $chat->id, 'chat');
                    }

                    if (!$DB->record_exists('forum', array('course' => $courseid, 'name' => "Forum"))) {

                        $forum = new stdClass();
                        $forum->course = $courseid;
                        $forum->name = "Forum";
                        $forum->intro = "Forum du cours ".$newcourse->shortname;
                        $forum->introformat = 1;
                        $forum->timemodified = time();
                        $forum->id = $DB->insert_record('forum', $forum);

                        local_create_module($courseid, $forum->id, 'forum');
                    }

                    if (!$DB->record_exists('assign', array('course' => $courseid, 'name' => "Devoir"))) {

                        $assign = new stdClass();
                        $assign->course = $courseid;
                        $assign->name = "Devoir";
                        $assign->intro = "Devoir du cours ".$newcourse->shortname;
                        $assign->introformat = 1;
                        $assign->timemodified = time();
                        $assign->id = $DB->insert_record('assign', $assign);

                        local_create_module($courseid, $assign->id, 'assign');
                    }
                        
                    local_create_block('autoattend', $courseid);
                    local_create_block('calendar_month', $courseid);
                    local_create_block('sharing_cart', $courseid);

                    course_integrity_check($courseid, null, null, true);
                }
            }
            $row++;
        }
    }

    fclose($fichiercsv);
}

function local_enrol_user(stdClass $instance, $userid, $roleid = null) {
    global $DB, $USER; // CFG necessary!!!

    $context = context_course::instance($instance->id, MUST_EXIST);
    $ue = new stdClass();
    $ue->enrolid      = $DB->get_record('enrol', array('enrol' => 'manual', 'courseid' => $instance->id))->id;
    $ue->status       = 0;
    $ue->userid       = $userid;
    $ue->timestart    = 0;
    $ue->timeend      = 0;
    $ue->modifierid   = $USER->id;
    $ue->timecreated  = time();
    $ue->timemodified = $ue->timecreated;

    if (!$DB->record_exists('user_enrolments', array('userid' => $userid, 'enrolid' => $ue->enrolid))) {

        $DB->insert_record('user_enrolments', $ue);
    }

    if (!$DB->record_exists('role_assignments',
            array('roleid' => $roleid, 'userid' => $userid, 'contextid' => $context->id))) {

        role_assign($roleid, $userid, $context->id);
    }
}

function local_create_module($courseid, $instanceid, $instancename) {
    
    global $DB;

    $now = time();

    $module = $DB->get_record('modules', array('name' => $instancename));
    $cm = new stdClass();
    $cm->course = $courseid;
    $cm->module = $module->id;
    $cm->instance = $instanceid;
    $sectionid = $DB->get_record('course_sections', array('course' => $courseid, 'section' => 0))->id;
    $cm->section = $sectionid;
    $cm->added = $now;
    $cm->visible = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->id = $DB->insert_record('course_modules', $cm);

    // Create context

    $context = new stdClass();
    $context->contextlevel = 70;
    $context->instanceid = $cm->id;
    $parent = $DB->get_record('context', array('instanceid' => $courseid, 'contextlevel' => 50));
    $parentpath = $parent->path;
    $context->path = null;
    $context->depth = $parent->depth + 1;
    $context->id = $DB->insert_record('context', $context);
    $context->path = $parentpath."/".$context->id;
    $DB->update_record('context', $context);
}

function local_create_block ($blockname, $courseid) {

    global $DB;

    $blockinstance = new stdClass;
    $blockinstance->blockname = $blockname;
    $blockinstance->parentcontextid = $DB->get_record('context',
            array('instanceid' => $courseid, 'contextlevel' => 50))->id;
    $blockinstance->showinsubcontexts = 0;
    $blockinstance->pagetypepattern = "course-view-*";
    $blockinstance->subpagepattern = null;
    $blockinstance->defaultregion = "side-pre";
    $blockinstance->defaultweight = 2;
    $blockinstance->configdata = '';

    if (!$DB->record_exists('block_instances',
            array('blockname' => $blockname, 'parentcontextid' => $blockinstance->parentcontextid))) {
        
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

        // Ensure the block context is created.
        context_block::instance($blockinstance->id);

        // If the new instance was created, allow it to do additional setup
        $block = block_instance($blockname, $blockinstance);
        if ($block) {
            $block->instance_create();
        }
    }
}