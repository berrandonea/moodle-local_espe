<title>
Inscription d'utilisateurs dans la plateforme depuis un fichier CSV
</title>

<script type="text/javascript" src="https://enp16.u-cergy.fr/lib/js/foToolTip.js"></script>

<style>

.dejacree {
    color : grey;
}

.dejacree a {
    color : grey;
}

 .dejacree a:hover {
    color : black;
 }

</style>

<?php
//define('CLI_SCRIPT', true);
require_once('config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');

header('Content-type: text/html; charset=utf-8');

redirect_if_major_upgrade_required();

$urlparams = array();
if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && optional_param('redirect', 1, PARAM_BOOL) === 0) {
    $urlparams['redirect'] = 0;
}
$PAGE->set_url('/', $urlparams);
$PAGE->set_course($SITE);
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');

// Prevent caching of this page to stop confusion when changing page after making AJAX changes
$PAGE->set_cacheable(false);

if ($CFG->forcelogin) {
    require_login();
} else {
    user_accesstime_log();
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

/// If the site is currently under maintenance, then print a message
if (!empty($CFG->maintenance_enabled) and !$hassiteconfig) {
    print_maintenance_message();
}

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect($CFG->wwwroot .'/'. $CFG->admin .'/index.php');
}

if (get_home_page() != HOMEPAGE_SITE) {
    // Redirect logged-in users to My Moodle overview if required
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && optional_param('redirect', 1, PARAM_BOOL) === 1) {
        redirect($CFG->wwwroot .'/my/');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
        $PAGE->settingsnav->get('usercurrentsettings')->add(get_string('makethismyhome'), new moodle_url('/', array('setdefaulthome'=>true)), navigation_node::TYPE_SETTING);
    }
}

if (isloggedin()) {
    add_to_log(SITEID, 'course', 'view', 'view.php?id='.SITEID, SITEID);
}

/// If the hub plugin is installed then we let it take over the homepage here
if (file_exists($CFG->dirroot.'/local/hub/lib.php') and get_config('local_hub', 'hubenabled')) {
    require_once($CFG->dirroot.'/local/hub/lib.php');
    $hub = new local_hub();
    $continue = $hub->display_homepage();
    //display_homepage() return true if the hub home page is not displayed
    //mostly when search form is not displayed for not logged users
    if (empty($continue)) {
        exit;
    }
}

$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('');
$PAGE->set_pagelayout('frontpage');

$editing = $PAGE->user_is_editing();
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);

$courserenderer = $PAGE->get_renderer('core', 'course');


//Ouverture du fichier CSV
echo "<h1>Inscription d'utilisateurs depuis le fichier CSV</h1>";

$fichiercsv = fopen('inscriptions/inscriptions.csv', 'r');

$row = 1;
if ($fichiercsv == FALSE) {
    echo "Impossible d'ouvrir le fichier CSV<br>";
} else {   
    
    while (($data = fgetcsv($fichiercsv, 200, ";")) !== FALSE) {
        
        
        //On regarde si un utilisateur a déjà ce mail
        $sql = "SELECT id FROM mdl_user WHERE email = '".$data[3]."'";
        echo "$sql<br>";        
        $previoususer = $DB->get_record_sql($sql);
        
        if ($previoususer) {
            echo "Cet utilisateur est déjà inscrit à la plateforme.<br>";
        } else {            
            /* BRICE - Commenté car on veut les mêmes logins que l'an dernier
            //Création de son login
            $firstname = strtolower(str_replace(" ", "", $data[1]));
            $lastname = strtolower(str_replace(" ", "", $data[0]));            
            $lastnamelength = strlen($lastname);
            $firstnamefirstletter = substr($firstname, 0, 1);
            
            if ($lastnamelength > 4) {                
                $login = substr($lastname, 0, 5).$firstnamefirstletter;
            } else {
                $login = $lastname.substr($firstname, 0, 6 - $lastnamelength);
            }
            
            $compteur = system("cat compteur");
            if ($compteur < 10) {
                $compteuraveczeros = "00".$compteur;
            } else if ($compteur < 100) {
                $compteuraveczeros = "0".$compteur;
            } else {
                $compteuraveczeros = $compteur;
            }
            
            $login .= $compteuraveczeros;            
            echo "Login : $login<br>";            
            
            $compteur++;
            $command = "echo '$compteur' > compteur";   
            echo "$command<br>";
            system($command);*/
            
            
            //Inscription dans la base de données            
            $user = new StdClass();
            $user->auth = 'manual';
            $user->confirmed = 1;
            $user->mnethostid = 1;
            $user->email = $data[3];
            $user->username = strtolower($data[2]);
            //$user->password = "$2y$10\$seFhZ3DHf5lmNiCGsCC.Q.vrfmyFhFHM2JqX78oXGDxNTpju4scta";
            $user->lastname = ucwords(strtolower($data[0]));
            $user->firstname = ucwords(strtolower($data[1]));
            $user->timecreated = time();
            $user->timemodified = time();
            //echo "Nouvel(le) étudiant(e) : $firstname $lastname ($studentuid)\n<br>";
            print_object($user);
            
            $user->id = $DB->insert_record('user', $user);
            echo "Insertion OK<br><br>";
                        
            //On force le changement du mot de passe dès la prochaine connexion
            //set_user_preference('auth_forcepasswordchange', 1, $usernew);
            $sql = "INSERT INTO mdl_user_preferences (userid, name, value) VALUES ($user->id, 'auth_forcepasswordchange', 1)";
            echo "$sql<br>";
            $DB->execute($sql);
            echo "Le changement du mot de passe sera imposé à la première connexion.<br>";
            
            //Envoi d'un e-mail à l'étudiant
            setnew_password_and_mail($user);
            

            /*
            $to  = $data[2];
            $subject = 'Inscription à la Plateforme pédagogique';
            $message = "Bonjour. Vous avez été inscrit(e) à la plateforme pédagogique de l'Université de Cergy-Pontoise. "
                    . "Votre nom d'utilisateur est '$login' et votre mot de passe est 123456§rT. "
                    . "Nous vous invitons à le modifier dès votre première connexion. Bienvenu(e) à l'Université de Cergy-Pontoise.";            
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            
            echo "Envoi d'un message à l'adresse $to<br>";
            if(mail($to, $subject, $message, $headers)) 
            { 
                   echo "<fieldset style='padding : 10px; width: 98%;font-weight : bold; background-color:green; color:white;''>
                   L'e-mail a bien été envoyé à l'étudiant(e).</fieldset>";
            } 
            else 
            { 
                   echo "<fieldset style='padding : 10px; width: 98%;font-weight : bold; background-color:red; color:white;''>
                   Erreur d'envoi du message à l'étudiant(e).</fieldset>";
            }
             * 
             */
            
        }
        
        
        
        
        
    }
    fclose($fichiercsv);
}






?>
