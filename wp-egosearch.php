<?php
/*
Plugin Name: WP egosearch
Plugin URI: http://smkn.xsrv.jp/blog/2015/03/wordpress-plugin-called-wp-egosearch/
Description: Displays the egosearch(search your site URL/sitename) results of twitter in the dashboard.
Version: 1.1.0
Author: smkn
Author URI: http://smkn.xsrv.jp/blog/
License: GPL2 or later
*/

/* Copyright 2015 smkn (email : smkn.xxx@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class wpEgosearch {
	const WPES_PLUGIN_NAME = 'WP egosearch';
	const WPES_PLUGIN_VERSION = '1.1.0';

	public $site_url;
	public $site_name;
	public $consumer_key;
	public $consumer_secret;
	public $dissearched = array();
	public $read_count;

	function __construct(){
		$this->site_url = preg_replace('/^https?:\/\//iu', '', get_option('siteurl'));
		$this->site_name = get_option('blogname');
		$this->consumer_key = (get_option('wpes_consumer_key'))?get_option('wpes_consumer_key'):'';
		$this->consumer_secret = (get_option('wpes_consumer_secret'))?get_option('wpes_consumer_secret'):'';
		$this->dissearched = (get_option('wpes_dissearched'))?explode(',', get_option('wpes_dissearched')):array();
		$this->read_count = (get_option('wpes_count'))?get_option('wpes_count'):'10';
		load_plugin_textdomain('wp-egosearch', false, basename(dirname(__FILE__)).'/languages');
		add_action('wp_dashboard_setup', array(&$this, 'wpes_setup'));
	}
	public function wpes_setup(){
		wp_add_dashboard_widget('wpes_views', self::WPES_PLUGIN_NAME, array(&$this, 'wpes_views'), array(&$this, 'wpes_configure'));
	}

	public function wpes_views(){
		if(!empty($this->consumer_key) && !empty($this->consumer_secret)){
			/* get bearer token */
			$headers = array(
				'POST /oauth2/token HTTP/1.1',
				'Host: api.twitter.com',
				'User-Agent: '.self::WPES_PLUGIN_NAME.self::WPES_PLUGIN_VERSION,
				'Authorization: Basic '.base64_encode(urlencode(esc_attr($this->consumer_key)).':'.urlencode(esc_attr($this->consumer_secret))),
				'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
			);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://api.twitter.com/oauth2/token/');
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$response = curl_exec($ch);
			curl_close($ch);

			if($response === false){
				_e('curl - Token could not be retrieved.', 'wp-egosearch').'<br />';
				echo 'curl error:'.curl_error($ch);
			} else {
				$bearer = json_decode($response);
				if(isset($bearer->errors)){
					_e('Token could not be retrieved.', 'wp-egosearch').'<br />';
					echo 'bearer token error:'.$bearer->errors[0]->message;
				}
			}

			if($response !== false && !isset($bearer->errors)){
				/* get search results */
				$q_str = '"'.urlencode($this->site_url).'"+OR+"'.urlencode($this->site_name).'"';
				$dissearch_str = '';
				if(!empty($this->dissearched)){
					foreach($this->dissearched as $v){
						$dissearch_str .= '+-"'.urlencode(trim($v));
					}
				}
				$url = 'https://api.twitter.com/1.1/search/tweets.json?q='.$q_str.$dissearch_str.'&include_entities=true&result_type=recent&count='.esc_attr($this->read_count);
				$headers = array(
					'GET /1.1/search/tweets.json? HTTP/1.1',
					'Host: api.twitter.com',
					'User-Agent: '.self::WPES_PLUGIN_NAME.self::WPES_PLUGIN_VERSION,
					'Authorization: Bearer '.$bearer->access_token
				);

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$response = curl_exec($ch);
				curl_close($ch);

				if($response === false){
					_e('curl - Search results could not be obtained.', 'wp-egosearch').'<br />';
					echo 'curl response error:'.curl_error($ch);
				} else {
					$searched = json_decode($response);
					if(isset($searched->errors)){
						_e('Search results could not be obtained.', 'wp-egosearch').'<br />';
						echo 'search error:'.$searched->errors[0]->message;
					}
				}

				if($response !== false && !isset($searched->errors)){
					/* format results */
					$ego_data = array();
					foreach($searched->statuses as $k => $v){
						$ego_data[$k]['post_id'] = $v->id_str;
						$ego_data[$k]['date'] = date("Y.m/d H:i:s", strtotime($v->created_at));
						$ego_data[$k]['user'] = $v->user->screen_name;
						$ego_data[$k]['text'] = $v->text;
					}

					/* print results */
					echo '<div class="widget"><ul>';
					if(count($ego_data) > 0){
						foreach($ego_data as $v){
							echo '<li>';
							echo '<a href="https://twitter.com/'.$v['user'].'/status/'.$v['post_id'].'" target="_blank">@'.$v['user'].' said. - at '.$ego_data[$k]['date'].'</a>';
							echo '<p style="margin:0">'.$v['text'].'</p>';
							echo '</li>';
						}
					} else {
						_e('There was no corresponding tweet.', 'wp-egosearch');
					}
					echo '</ul></div>';
				}
			}
		} else {
			_e('Configure this widget by clicking the link in its upper right corner.', 'wp-egosearch');
		}
	}

	function wpes_configure() {
		if(isset( $_POST['wpes_consumer_key'])){
			update_option('wpes_consumer_key', sanitize_text_field($_POST['wpes_consumer_key']));
			update_option('wpes_consumer_secret', sanitize_text_field($_POST['wpes_consumer_secret']));
			update_option('wpes_dissearched', sanitize_text_field($_POST['wpes_dissearched']));
			update_option('wpes_count', sanitize_text_field($_POST['wpes_count']));
		}
		$feed_url = $this->consumer_key;

		echo '<label for="wpes_consumer_key"><span>twitter consumer key</span><input type="text" name="wpes_consumer_key" id="wpes_consumer_key" value="'.esc_textarea($this->consumer_key).'" size="45" /></label>';
		echo '<label for="wpes_consumer_secret"><span>twitter consumer secret</span><input type="text" name="wpes_consumer_secret" id="wpes_consumer_secret" value="'.esc_textarea($this->consumer_secret).'" size="45" /></label>';
		echo '<label for="wpes_dissearched"><span>'._e('Exclude keywords(Comma-separated)', 'wp-egosearch').'</span><input type="text" name="wpes_dissearched" id="wpes_dissearched" value="'.esc_textarea(implode(',', $this->dissearched)).'" size="45" placeholder="one thing,two,three" /></label>';
		echo '<label for="wpes_count"><span>'._e('Number to get(Up to 100. ※Only tweet for less than one week)', 'wp-egosearch').'</span><input type="text" name="wpes_count" id="wpes_count" value="'.esc_textarea($this->read_count).'" size="45" style="margin-bottom:10px;" /></label>';
	}
}
new wpEgosearch();

