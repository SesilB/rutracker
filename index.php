<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
class Rutracker{
	private $login;
	private $password;
	public function __construct($login,$password){
		$this->login = $login;
		$this->password = $password;
		if(!$this->authorization()){
			echo 'wrong username or password';
			exit;
		}
	}
	public function search(){
		$query = $_GET['query'];
		if(isset($_GET['page'])){
			$page = $_GET['page'];
		}else{
			$page = 1;
		}
		if(isset($_GET['categories'])){
			$categories = $_GET['categories'];
		}else{
			$categories = '-1';
		}
		$start = (intval($page) - 1)*50;
		$url = 'http://rutracker.org/forum/tracker.php?nm='.$query.'&start='.$start.'&f='.$categories;
		$post_data = 'start=sss';
		$result = $this->curl($url,$post_data);
		$pages = substr($result,strpos($result,'Результатов поиска:')+37);
		$pages = ceil(intval(substr($pages,0,strpos($pages,'"')))/50);
		$result = substr($result,strpos($result,'<table class="forumline tablesorter" id="tor-tbl">'));
		$result = substr($result,0,strpos($result,'</table>'));
		preg_match_all("/<a class=\"gen f\" href=\"tracker\.php\?f=(.*)\">(.*)<\/a[\s\S]*class=\"med tLink hl-tags bold\"[\s\S]*\">(.*)<\/a[\s\S]*class=\"small tr-dl dl-stub\" href=\".*t=(.*)\">(.*)<\/a>/U",$result,$matches);
		$data = array();
		foreach($matches[2] as $key=>$category){
			$title = $matches[3][$key];
			$link = 'http://dl.rutracker.org/forum/dl.php?t='.$matches[4][$key];
			$size = $matches[5][$key];
			$category_id = $matches[1][$key];
			$size = trim(str_replace('&#8595;','',$size));
			$data[] = array('title'=>$title,'category'=>$category,'category_id'=>$category_id,'link'=>$link,'size'=>$size);
		}
		return array('page'=>intval($page),'pages'=>$pages,'torrents'=>$data);
	}
	public function get_details(){
		$id = $_GET['id'];
		$url = 'http://rutracker.org/forum/viewtopic.php?t='.$id;
		$content = $this->curl($url,false);
		$content = substr($content,strpos($content,'<div class="post_body"'));
		$content = substr($content,0,strpos($content,'<div class="sp-wrap">')).'</div>';
		echo $content;
	}
	public function download(){
		$id = $_GET['id'];
		$id = str_replace('http://dl.rutracker.org/forum/dl.php?t=','',$id);
		$id = str_replace('dl.php?t=','',$id);
		$url = 'http://dl.rutracker.org/forum/dl.php?t='.$id;
		$torrent = $this->curl($url,false);
		$destination = $id.'.torrent';
		$file = fopen('../torrents/'.$destination, "w+");
		fputs($file, $torrent);
		fclose($file);
		return $destination;
	}
	public function getCategoties(){
		$items['groups'] = array();
		$result = $this->curl('http://rutracker.org/forum/search.php');
		$result = substr($result,strpos($result,'<fieldset id="fs">'));
		$result = substr($result,0,strpos($result,'</fieldset>'));
		preg_match_all("/<optgroup label=\"&nbsp;(.*)\">([\s\S]*)<\/optgroup>/U",$result,$group_matches);
		foreach($group_matches[1] as $i=>$group_title){
			$categories = array();
			$subcategories = array();
			preg_match_all("/<option.*value=\"(.*)\"(.*)>(.*)<\/option>/U",$group_matches[2][$i],$category_matches);
			foreach($category_matches[3] as $n=>$subcategory_title){
				if(stristr($category_matches[2][$n],'root_forum')){
					$category_title = $subcategory_title;
					$categories[$group_title][] = array('title'=>$category_title,'id'=>$category_matches[1][$n]);
				}else{
					$subcategories[$category_title][] = array('title'=>str_replace(array(' |- ','&nbsp;'),'',$subcategory_title),'id'=>$category_matches[1][$n]);
				}
			}
			foreach($categories[$group_title] as $key=>$group_category){
				if(isset($subcategories[$group_category['title']])){
					$categories[$group_title][$key]['subcategories'] =  $subcategories[$group_category['title']];
				}
			}
			$items['groups'][] = array('title'=>$group_title,'categories'=>$categories[$group_title]);
		}
		return $items;
	}
	private function authorization(){
		$post_data = 'login_username='.$this->login.'&login_password='.$this->password .'&login=%D0%92%D1%85%D0%BE%D0%B4';
		$url = 'http://rutracker.org/forum/login.php';
		$result = $this->curl($url,$post_data);
		if(strstr($result,'<span class="logged-in-as-cap">Вы зашли как:</span>')){
			return true;
		}else{
			return false;
		}
	}
	private function curl($url,$post=false){
		$curl = curl_init();
		$headers = array('Referer: http://rutracker.org/forum/index.php','Origin: http://rutracker.org','Content-Type: application/x-www-form-urlencoded');
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl ,CURLOPT_HTTPHEADER,$headers);
		if($post){
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__).'/'.md5($this->login . $this->password).'.txt');
		curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__).'/'.md5($this->login . $this->password).'.txt');
		$out = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);
		$out = iconv('windows-1251','utf-8',$out);
		return $out;
	}
}
if(isset($_GET['login']) && isset($_GET['password'])){
	$rutracker = new Rutracker($_GET['login'],$_GET['password']);
	$method = $_GET['action'];
	if(method_exists($rutracker,$method)){
		$result = $rutracker->$method();
		echo json_encode($result);
	}
}
?>