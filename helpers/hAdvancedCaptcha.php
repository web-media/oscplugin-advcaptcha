<?php
/* Developed by WEBmods
 * Zagorski oglasnik j.d.o.o. za usluge | www.zagorski-oglasnik.com
 *
 * License: GPL-3.0-or-later
 * More info in license.txt
*/

if(!defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

/* Get plugin folder. */
function advcaptcha_plugin() {
    return ADVCAPTCHA_FOLDER;
}

/* Get plugin file URL. */
function advcaptcha_url($file = '') {
    return osc_base_url().'oc-content/plugins/'.advcaptcha_plugin().'/'.$file;
}

function advcaptcha_pref($key) {
    return osc_get_preference($key, ADVCAPTCHA_PREF);
}

function advcaptcha_admin_routes() {
    return array('advancedcaptcha', 'advancedcaptcha-post');
}

function advcaptcha_is_admin() {
    if(!Params::existParam('route')) {
        return false;
    }

    return in_array(Params::getParam('route'), advcaptcha_admin_routes());
}

function advcaptcha_is_route($name) {
    return (Params::getParam('route') == $name);
}

/* Get list of positions. */
function advcaptcha_positions() {
    return array(
        'login' => array(
            'name' => __('Login', advcaptcha_plugin()),
            'hook_show' => 'advcaptcha_hook_login',
            'hook_post' => 'before_validating_login',
            'page' => 'login',
            'action' => null,
            'redirect' => osc_user_login_url(),
            'file' => 'user-login.php'
        ),
        'register' => array(
            'name' => __('Register', advcaptcha_plugin()),
            'hook_show' => 'user_register_form',
            'hook_post' => 'before_user_register',
            'page' => 'register',
            'action' => 'register',
            'redirect' => osc_register_account_url()
        ),
        'recover' => array(
            'name' => __('Forgotten password', advcaptcha_plugin()),
            'hook_show' => 'advcaptcha_hook_recover',
            'hook_post' => 'init_login',
            'page' => 'login',
            'action' => 'recover',
            'redirect' => osc_recover_user_password_url(),
            'file' => 'user-recover.php'
        ),
        'contact' => array(
            'name' => __('Contact', advcaptcha_plugin()),
            'hook_show' => 'contact_form',
            'hook_post' => 'init_contact',
            'page' => 'contact',
            'action' => null,
            'redirect' => osc_contact_url()
        ),
        'item_add' => array(
            'name' => __('Add an item', advcaptcha_plugin()),
            'hook_show' => 'advcaptcha_hook_item',
            'hook_post' => 'pre_item_post',
            'page' => 'item',
            'action' => 'item_add',
            'redirect' => osc_item_post_url(),
            'file' => 'item-post.php'
        ),
        'item_edit' => array(
            'name' => __('Edit an item', advcaptcha_plugin()),
            'hook_show' => 'advcaptcha_hook_item',
            'hook_post' => 'pre_item_post',
            'page' => 'item',
            'action' => 'item_edit',
            'redirect' => null,
            'file' => 'item-post.php'
        ),
        'comment' => array(
            'name' => __('Add a comment', advcaptcha_plugin()),
            'hook_show' => 'advcaptcha_hook_comment',
            'hook_post' => 'pre_item_add_comment_post',
            'page' => 'item',
            'action' => null,
            'redirect' => null,
            'file' => 'item.php/item-sidebar.php'
        )
    );
}

/* Get list of enabled positions. */
function advcaptcha_positions_enabled() {
    $positions = advcaptcha_positions();
    foreach($positions as $id => $pos) {
        $type = advcaptcha_pref('show_'.$id);
        if(!$type) {
            unset($positions[$id]);
        } else {
            $positions[$id]['type'] = $type;
        }
    }

    return $positions;
}

/* Get preferences from View. */
function advcaptcha_get_preferences() {
    return View::newInstance()->_get('advcaptcha_preferences');
}

/* Get Web routes. */
function advcaptcha_web_routes() {
    return array('advancedcaptcha-refresh');
}

function advcaptcha_types() {
    return array('google', 'math', 'text', 'qna');
}

/* Get session key. */
function advcaptcha_session_key() {
    $page = (Params::getParam('page') != '') ? Params::getParam('page') : null;
    $action = (Params::getParam('action') != '') ? Params::getParam('action') : null;

    return 'advcaptcha_'.$page.'_'.$action;
}

/* Verify reCAPTCHA V3. */
function advcaptcha_verify_google($response) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array('secret' => advcaptcha_pref('recaptcha_secret_key'), 'response' => $response);


    $recaptcha = osc_file_get_contents($url, $data);
    $recaptcha = json_decode($recaptcha);

    return ($recaptcha->score >= (float) advcaptcha_pref('recaptcha_threshold'));
}

/* Generate math captcha. */
function advcaptcha_generate_math($max = 10) {
    $num1 = rand(1, $max);
    $num2 = rand(1, $max);
    $ans = $num1 + $num2;

    return array('num1' => $num1, 'num2' => $num2, 'ans' => $ans, 'type' => 'math');
}

/* Verify math captcha. */
function advcaptcha_verify_math($problem, $answer) {
    return ((int) $answer === $problem['ans']);
}

/* Generate Q&A captcha. */
function advcaptcha_generate_qna() {
    $questions = unserialize(advcaptcha_pref('questions'));
    $count = count($questions);
    shuffle($questions);
    $question = $questions[0];
    unset($questions);

    return array('ans' => $question[1], 'question' => $question[0], 'count' => $count, 'type' => 'qna');
}

/* Generate Q&A captcha and exclude a question. */
function advcaptcha_generate_qna_refresh($exclude) {
    $questions = unserialize(advcaptcha_pref('questions'));
    $count = count($questions);

    // IDEA: This (probably) can be improved. Both from safety and speed sides...
    foreach($questions as $key => $q) {
        if($q[0] == $exclude) {
            unset($questions[$key]);
        }
    }

    shuffle($questions);
    $question = $questions[0];
    unset($questions);

    return array('ans' => $question[1], 'question' => $question[0], 'count' => $count, 'type' => 'qna');
}

/* Verify Q&A captcha. */
function advcaptcha_verify_qna($problem, $answer) {
    return (trim(strtolower($answer)) == trim(strtolower($problem['ans'])));
}

/* Generate text captcha. */
function advcaptcha_generate_text($length = 5) {
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $max = strlen($chars) - 1;
    $random = '';
    for($i = 0; $i <= $length; $i++) {
        $random .= substr($chars, rand(0, $max), 1);
    }

    $image = advcaptcha_generate_text_img($random);

    return array('ans' => $random, 'img' => $image, 'type' => 'text');
}

/* Verify text captcha. */
function advcaptcha_verify_text($problem, $answer) {
    return (trim(strtolower($answer)) == trim(strtolower($problem['ans'])));
}

/* Generate text captcha image. */
function advcaptcha_generate_text_img($string, $width = 250, $height = 80, $fontsize = 24) {
    $font = ADVCAPTCHA_PATH.'assets/web/ttf/font.ttf';
    $background = ADVCAPTCHA_PATH.'assets/web/img/pattern.jpg';

    $captcha = imagecreatetruecolor($width, $height);
    list($bx, $by) = getimagesize($background);
    $bx = ($bx - $width < 0) ? 0 : rand(0, $bx - $width);
    $by = ($by - $height < 0) ? 0 : rand(0, $by - $height);
    $background = imagecreatefromjpeg($background);
    imagecopy($captcha, $background, 0, 0, $bx, $by, $width, $height);

    $text_size = imagettfbbox($fontsize, 0, $font, $string);
    $text_width = max([$text_size[2], $text_size[4]]) - min([$text_size[0], $text_size[6]]);
    $text_height = max([$text_size[5], $text_size[7]]) - min([$text_size[1], $text_size[3]]);

    $centerX = ceil(($width - $text_width) / 2);
    $centerX = $centerX < 0 ? 0 : $centerX;
    $centerX = ceil(($height - $text_height) / 2);
    $centerY = $centerX < 0 ? 0 : $centerX;

    if(rand(0, 1)) {
        $centerX -= rand(0,55);
    } else {
        $centerX += rand(0,55);
    }
    $colornow = imagecolorallocate($captcha, rand(0, 100), rand(0, 100), rand(0, 100));
    imagettftext($captcha, $fontsize, rand(-10, 10), $centerX, $centerY, $colornow, $font, $string);

    ob_start();
    imagejpeg($captcha);
    imagedestroy($captcha);
    $contents = ob_get_contents();
    ob_end_clean();

    $image = 'data:image/jpeg;base64,'.base64_encode($contents);
    return $image;
}
