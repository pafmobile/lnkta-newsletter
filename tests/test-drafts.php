<?php
error_reporting(E_ALL);define('ABSPATH',__DIR__.'/');
function add_action(...$a){}function add_filter(...$a){}
function plugin_basename($f){return 'lnkta-newsletter-plugin/'.basename($f);}
$GLOBALS['opts']=['lnkta_newsletter_settings'=>['api_key'=>'test-key','sender_name'=>'Test','sender_email'=>'sender@example.com','list_id'=>1]];
$GLOBALS['meta']=[];$GLOBALS['api_calls']=0;$GLOBALS['response_code']=201;
function get_option($n,$d=false){return $GLOBALS['opts'][$n]??$d;}
function add_option($n,$v,...$a){if(isset($GLOBALS['opts'][$n]))return false;$GLOBALS['opts'][$n]=$v;return true;}
function delete_option($n){unset($GLOBALS['opts'][$n]);}
function get_post_meta($id,$n,$single=true){return $GLOBALS['meta'][$id][$n]??'';}
function update_post_meta($id,$n,$v){$GLOBALS['meta'][$id][$n]=$v;return true;}
function delete_post_meta($id,$n){unset($GLOBALS['meta'][$id][$n]);}
function current_user_can(...$a){return true;}
function get_post_type($id){return 'newsletter';}
function check_ajax_referer(...$a){}
function get_post_field(...$a){return 'Intro';}
function get_the_title($id){return 'Test newsletter';}
function get_bloginfo(...$a){return 'LNKTA';}
function home_url($p=''){return 'https://example.com'.$p;}
function wp_parse_url($v,$c){return parse_url($v,$c);}
function wp_get_attachment_url($id){return false;}
function wp_unslash($v){return $v;}
function sanitize_text_field($v){return strip_tags($v);}
function sanitize_hex_color($v){return preg_match('/^#[a-f0-9]{3}([a-f0-9]{3})?$/i',$v)?$v:null;}
function absint($v){return abs((int)$v);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v,...$a){return json_encode($v,...$a);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_attr($v);}
function do_shortcode($v){return $v;}function wpautop($v){return '<p>'.$v.'</p>';}function wp_kses_post($v){return $v;}
class WP_Error{private $c;private $m;function __construct($c,$m){$this->c=$c;$this->m=$m;}function get_error_code(){return $this->c;}function get_error_message(){return $this->m;}}
function is_wp_error($v){return $v instanceof WP_Error;}
class JsonResult extends Exception{public $success;public $data;function __construct($s,$d){$this->success=$s;$this->data=$d;}}
function wp_send_json_success($d,...$a){throw new JsonResult(true,$d);}function wp_send_json_error($d,...$a){throw new JsonResult(false,$d);}
function wp_remote_request($url,$args){$GLOBALS['api_calls']++;$GLOBALS['payload']=json_decode($args['body'],true);$GLOBALS['method']=$args['method'];return ['response'=>['code'=>$GLOBALS['response_code']],'body'=>json_encode($GLOBALS['response_code']===201?['id'=>100+$GLOBALS['api_calls']]:['message'=>'Unrecognised IP address 192.0.2.10'])];}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
require __DIR__.'/../lnkta-newsletter-plugin/lnkta-newsletter-plugin.php';
function check($ok,$s){if(!$ok)throw new Exception('FAIL '.$s);echo 'PASS '.$s."\n";}
$p=new LNKTA_Newsletter_Plugin();
function draft($token){global $p;$_POST=['newsletter_id'=>1,'subject'=>'Weekly news','request_id'=>$token];try{$p->create_draft();}catch(JsonResult $r){return $r;}}
$a=draft('11111111-1111-4111-8111-111111111111');check($a->success&&$a->data['campaign_id']===101,'first request creates draft');
$b=draft('11111111-1111-4111-8111-111111111111');check($b->success&&$b->data['campaign_id']===101&&$GLOBALS['api_calls']===1,'retry of same request reuses draft');
$c=draft('22222222-2222-4222-8222-222222222222');check($c->success&&$c->data['campaign_id']===102&&$GLOBALS['api_calls']===2,'new click creates a new draft for same newsletter');
check(get_post_meta(1,'_lnkta_brevo_campaign')===102,'last campaign is recorded without blocking future drafts');
check($GLOBALS['method']==='POST'&&!isset($GLOBALS['payload']['scheduledAt'])&&!isset($GLOBALS['payload']['sendAtBestTime']),'creates drafts without scheduling');
check(substr_count($GLOBALS['payload']['htmlContent'],'{{ unsubscribe }}')===1,'campaign keeps personal unsubscribe placeholder');
check(!isset($GLOBALS['opts']['lnkta_brevo_lock_1']),'lock released after success');
$GLOBALS['opts']['lnkta_brevo_lock_1']=time();$d=draft('33333333-3333-4333-8333-333333333333');check(!$d->success&&$GLOBALS['api_calls']===2,'concurrent request blocked');unset($GLOBALS['opts']['lnkta_brevo_lock_1']);
$GLOBALS['response_code']=401;$e=draft('44444444-4444-4444-8444-444444444444');check(!$e->success&&strpos($e->data['message'],'192.0.2.10')!==false,'Brevo error detail retained');
check(!isset($GLOBALS['opts']['lnkta_brevo_lock_1']),'lock released after API rejection');
$GLOBALS['response_code']=201;$e=draft('44444444-4444-4444-8444-444444444444');check($e->success,'rejected request can retry after correction');
$f=draft('short');check(!$f->success,'invalid request identifier rejected');
// An older permanent lock can no longer disable the button indefinitely.
$GLOBALS['opts']['lnkta_brevo_lock_1']=time()-300;$g=draft('55555555-5555-4555-8555-555555555555');check($g->success,'stale legacy lock recovers');
echo "Draft tests passed.\n";
