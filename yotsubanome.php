<?php
/*＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊
　　　　四葉の芽画像掲示板

yotsubanome.php*/$ver = "v0.7.8.1.0004 β lot.100404";/*

これがコアシステムです。　四葉の芽スクリプト
このスクリプトはレッツPHP!<http://php.s3.to/>のgazou.phpを改造したものです。
配布条件はレッツPHP!に準じます。改造、再配布は自由にどうぞ。
このスクリプトに関する質問はレッツPHP!にしないようにお願いします。
最新版は<http://4ch.irc.su/+4/script/>で配布しています。
ご質問は準備板＠四葉の芽<http://4ch.irc.su/+4/test/>までどうぞ。

--【スパーキー（④ ^ヮ^）】◆FCr.DTJy2k◆◆/ODv/gdbGrBJVTTiLB/IBFugUUM=◆四葉の芽◇ちゃんねる ## 管理者 ##
＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊＊
*/
define("PIXMICAT_VER", 'yotsubanome '.$ver); // 版本資訊文字
/*
Pixmicat! : 圖咪貓貼圖版程式
http://pixmicat.openfoundry.org/
版權所有 © 2005-2009 Pixmicat! Development Team

版權聲明：
此程式是基於レッツPHP!<http://php.s3.to/>的gazou.php、
双葉ちゃん<http://www.2chan.net>的futaba.php所改寫之衍生著作程式，屬於自由軟體，
以The Clarified Artistic License作為發佈授權條款。
您可以遵照The Clarified Artistic License來自由使用、散播、修改或製成衍生著作。
更詳細的條款及定義請參考隨附"LICENSE"條款副本。

發佈這一程式的目的是希望它有用，但沒有任何擔保，甚至沒有適合特定目的而隱含的擔保。
關於此程式相關的問題請不要詢問レッツPHP!及双葉ちゃん。

如果您沒有隨著程式收到一份The Clarified Artistic License副本，
請瀏覽http://pixmicat.openfoundry.org/license/以取得一份。

最低運行需求：
PHP 4.3.0 / 27 December 2002
GD Version 2.0.28 / 21 July 2004

建議運行環境：
PHP 4.4.9 或更高版本並開啟 GD 和 Zlib 支援，如支援 ImageMagick 建議使用
安裝 PHP 編譯快取套件 (如eAccelerator, XCache, APC) 或其他快取套件 (如memcached) 更佳
如伺服器支援 SQLite, MySQL, PostgreSQL 等請盡量使用

設置方法：
根目錄的權限請設為777，
首先將pixmicat.php執行過一遍，必要的檔案和資料夾權限皆會自動設定，
自動設定完成後請刪除或註解起來此檔案底部之init(); // ←■■！程式環境初始化(略)一行，
然後再執行一遍pixmicat.php，即完成初始化程序，可以開始使用。

細部的設定請打開config.php參考註解修改，另有 Wiki (http://pixmicat.wikidot.com/pmcuse:config)
說明條目可資參考。
*/

/* Enviorment Settings */
// Do not change unless you renamed the directories


define("PHP_DIRECTORY", '../test/'); // yotsubanome "C:\windows\system\"

/* Include */
include_once(PHP_DIRECTORY.'lib/lib_env.php'); // Enviorment Settings
include_once(PHP_DIRECTORY.'config.php'); // 引入設定檔
include_once(PHP_DIRECTORY.'lib/lib_language.php'); // 引入語系
include_once(PHP_DIRECTORY.'lib/lib_common.php'); // 引入共通函式檔案
include_once(PHP_DIRECTORY.'lib/lib_fileio.php'); // 引入FileIO
include_once(PHP_DIRECTORY.'lib/lib_pio.php'); // 引入PIO
include_once(PHP_DIRECTORY.'lib/lib_pms.php'); // 引入PMS
include_once(PHP_DIRECTORY.'lib/lib_pte.php'); // 引入PTE外部函式庫

$PTE = new PTELibrary(TEMPLATE_FILE); // PTE Library

if(version_compare(PHP_VERSION, '5.0.0', '>=')){ // PHP5+
	set_error_handler('PMCCore_errorHandler', E_ERROR | E_WARNING | E_USER_ERROR); // Handle Errors
}else{
	//---- set_error_handler('PMCCore_errorHandler');
	error_reporting(E_ALL); // show all errors for debugging
}

/* Lockdown [prevents posting] */
if(file_exists(PHP_DIRECTORY.'lockdown')){
	if($_POST['mode'] == 'usrdel' || $_GET['mode'] == 'latest' || $_GET['res']){
		echo "";
	}else{
		die('Posting temporarily disabled. Come back later!<br/>&mdash;四葉の芽チーム');
	}
}

/* 更新記錄檔檔案／輸出討論串 */
/* ログの全体更新 */
function updatelog($resno=0,$page_num=-1,$single_page=false){
	global $config, $PIO, $FileIO, $PTE, $PMS, $language, $LIMIT_SENSOR, $style_bar;

	$adminMode = adminAuthenticate('check') && $page_num != -1 && !$single_page; // 前端管理模式
	$adminFunc = ''; // 前端管理選擇
	if($adminMode){
		$adminFunc = '<select name="func"><option value="delete">'._T('admin_delete').'</option>';
		$funclist = array();
		$PMS->useModuleMethods('AdminFunction', array('add', &$funclist, null, null)); // "AdminFunction" Hook Point
		foreach($funclist as $f) $adminFunc .= '<option value="'.$f[0].'">'.$f[1].'</option>'."\n";
		$adminFunc .= '</select>';
	}
	$resno = intval($resno); // 編號數字化
	$page_start = $page_end = 0; // 靜態頁面編號
	$inner_for_count = 1; // 內部迴圈執行次數
	$RES_start = $RES_amount = $hiddenReply = $tree_count = $hiddenImage = $hiddenImagP = 0;
	$hiddenImagRP = 1; // o++
	$kill_sensor = $old_sensor = false; // 預測系統啟動旗標
	$arr_kill = $arr_old = array(); // 過舊編號陣列
	$pte_vals = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'','{$SELF}'=>PHP_SELF,
		'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel" />'._T('del_head'),
		'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" />',
		'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
		'{$DEL_PASS_TEXT}' => ($adminMode ? $adminFunc : '')._T('del_pass'),
		'{$DEL_PASS_FIELD}' => '<input class="inputtext" type="password" name="pwd" size="8" value="" />',
		'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'" />');
	if(is_file(DATA_DIR.CSV_SS)) $pte_vals += array('{$STYLE_BAR}' => '<br />Style '.$style_bar);

	if($resno) $pte_vals['{$RESTO}'] = $resno;

	if(!$resno){
		if($page_num==-1){ // remake模式 (PHP動態輸出多頁份)
			$threads = $PIO->fetchThreadList(); // 取得全討論串列表
			$PMS->useModuleMethods('ThreadOrder', array($resno,$page_num,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$threads_count = count($threads);
			$inner_for_count = $threads_count > PAGE_DEF ? PAGE_DEF : $threads_count;
			$page_end = ceil($threads_count / PAGE_DEF) - 1; // 頁面編號最後值
		}else{ // 討論串分頁模式 (PHP動態輸出一頁份)
			$threads_count = $PIO->threadCount(); // 討論串個數
			//---- if($page_num < 0 || ($page_num * PAGE_DEF) >= $threads_count) error(_T('page_not_found')); // $page_num超過範圍
			//++++---- Page 0 bug fix
			if($page_num!=0) if($page_num < 0 || ($page_num * PAGE_DEF) >= $threads_count) error(_T('page_not_found')); // $page_num超過範圍
			//++++----
			$page_start = $page_end = $page_num; // 設定靜態頁面編號
			$threads = $PIO->fetchThreadList($page_num * PAGE_DEF, PAGE_DEF); // 取出分頁後的討論串首篇列表
			$PMS->useModuleMethods('ThreadOrder', array($resno,$page_num,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$inner_for_count = count($threads); // 討論串個數就是迴圈次數
		}
	}else{
		if(!$PIO->isThread($resno)){ error(_T('thread_not_found')); }
		$AllRes = isset($_GET['page_num']) && $_GET['page_num']=='all'; // 是否使用 ALL 全部輸出

		// 計算回應分頁範圍
		$tree_count = $PIO->postCount($resno) - 1; // 討論串回應個數
		if($tree_count && RE_PAGE_DEF){ // 有回應且RE_PAGE_DEF > 0才做分頁動作
			if($page_num==='all'){ // show all
				$page_num = 0;
				$RES_start = 1;
				$RES_amount = $tree_count;
			}else{
				if($page_num==='RE_PAGE_MAX') $page_num = ceil($tree_count / RE_PAGE_DEF) - 1; // 特殊值：最末頁
				if($page_num < 0) $page_num = 0; // 負數
				if($page_num * RE_PAGE_DEF >= $tree_count) error(_T('page_not_found'));
				$RES_start = $page_num * RE_PAGE_DEF + 1; // 開始
				$RES_amount = RE_PAGE_DEF; // 取幾個
			}
		}elseif($page_num > 0) error(_T('page_not_found')); // 沒有回應的情況只允許page_num = 0 或負數
		else{ $RES_start = 1; $RES_amount = $tree_count; $page_num = 0; } // 輸出全部回應

		if(USE_RE_CACHE && !$adminMode){ // 檢查快取是否仍可使用 / 頁面有無更動
			$cacheETag = md5(($AllRes ? 'all' : $page_num).'-'.$tree_count); // 最新狀態快取用 ETag
			$cacheFile = './cache/'.$resno.'-'.($AllRes ? 'all' : $page_num).'.'; // 暫存快取檔位置
			$cacheGzipPrefix = extension_loaded('zlib') ? 'compress.zlib://' : ''; // 支援 Zlib Compression Stream 就使用
			$cacheControl = isset($_SERVER['HTTP_CACHE_CONTROL']) ? $_SERVER['HTTP_CACHE_CONTROL'] : ''; // 瀏覽器快取控制
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == '"'.$cacheETag.'"'){ // 再度瀏覽而快取無更動
				header('HTTP/1.1 304 Not Modified');
				header('ETag: "'.$cacheETag.'"');
				return;
			}elseif(file_exists($cacheFile.$cacheETag) && $cacheControl != 'no-cache'){ // 有(更新的)暫存快取檔存在 (未強制no-cache)
				header('X-Cache: HIT from Pixmicat');
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
				readfile($cacheGzipPrefix.$cacheFile.$cacheETag); return;
			}else{
				header('X-Cache: MISS from Pixmicat');
			}
		}
	}

	//++++---- Page 0 bug fix
	if($tree_count==0 && $page_end==-1) $page_end = 0;
	//++++----
	// 預測過舊文章和將被刪除檔案
	if(PIOSensor::check('predict', $LIMIT_SENSOR)){ // 是否需要預測
		$old_sensor = true; // 標記打開
		$arr_old = array_flip(PIOSensor::listee('predict', $LIMIT_SENSOR)); // 過舊文章陣列
	}
	$tmp_total_size = total_size(); // 目前附加圖檔使用量
	$tmp_STORAGE_MAX = STORAGE_MAX * (($tmp_total_size >= STORAGE_MAX) ? 1 : 0.96); // 預估上限值
	if(STORAGE_LIMIT && STORAGE_MAX > 0 && ($tmp_total_size >= $tmp_STORAGE_MAX)){
		$kill_sensor = true; // 標記打開
		$arr_kill = $PIO->delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // 過舊附檔陣列
	}

	$PMS->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], $resno)); // "ThreadFront" Hook Point
	$PMS->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], $resno)); // "ThreadRear" Hook Point

	// 生成靜態頁面一頁份內容
	for($page = $page_start; $page <= $page_end; $page++){
		$dat = ''; $pte_vals['{$THREADS}'] = '';
		head($dat, $resno);
		form($dat, $resno);
		// 輸出討論串內容
		for($i = 0; $i < $inner_for_count; $i++){
			// 取出討論串編號
			if($resno) $tID = $resno; // 單討論串輸出 (回應模式)
			else{
				if($page_num == -1 && ($page * PAGE_DEF + $i) >= $threads_count) break; // remake 超出索引代表已全部完成
				$tID = ($page_start==$page_end) ? $threads[$i] : $threads[$page * PAGE_DEF + $i]; // 一頁內容 (一般模式) / 多頁內容 (remake模式)
				$tree_count = $PIO->postCount($tID) - 1; // 討論串回應個數
				$RES_start = $tree_count - RE_DEF + 1; if($RES_start < 1) $RES_start = 1; // 開始
				$RES_amount = RE_DEF; // 取幾個
				$hiddenReply = $RES_start - 1; // 被隱藏回應數
			}

			// $RES_start, $RES_amount 拿去算新討論串結構 (分頁後, 部分回應隱藏)
			$tree = $PIO->fetchPostList($tID); // 整個討論串樹狀結構
			$tree_cut = array_slice($tree, $RES_start, $RES_amount); array_unshift($tree_cut, $tID); // 取出特定範圍回應
			$posts = $PIO->fetchPosts($tree_cut); // 取得文章架構內容
			$posts_img = $PIO->fetchPosts($tree); // o++
			$pte_vals['{$THREADS}'] .= arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno, $arr_kill, $arr_old, $kill_sensor, $old_sensor, true, $adminMode, $hiddenImage, $hiddenImagP, $hiddenImagRP, $posts_img); // 交給這個函式去搞討論串印出
		}
		$pte_vals['{$PAGENAV}'] = '<div id="page_switch">';

		// 換頁判斷
		$prev = ($resno ? $page_num : $page) - 1;
		$next = ($resno ? $page_num : $page) + 1;
		if($resno){ // 回應分頁
			if(RE_PAGE_DEF > 0){ // 回應分頁開啟
				$pte_vals['{$PAGENAV}'] .= '<table class="pages" border="1"><tr><td style="white-space: nowrap;">';
				$pte_vals['{$PAGENAV}'] .= ($prev >= 0) ? '<a href="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$prev.'">'._T('prev_page').'</a>' : _T('first_page');
				$pte_vals['{$PAGENAV}'] .= "</td><td>";
				if($tree_count==0) $pte_vals['{$PAGENAV}'] .= '[<b>0</b>] '; // 無回應
				else{
					for($i = 0, $len = $tree_count / RE_PAGE_DEF; $i < $len; $i++){
						if(!$AllRes && $page_num==$i) $pte_vals['{$PAGENAV}'] .= '[<b>'.$i.'</b>] ';
						else $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$i.'">'.$i.'</a>] ';
					}
					$pte_vals['{$PAGENAV}'] .= $AllRes ? '[<b>'._T('all_pages').'</b>] ' : ($tree_count > RE_PAGE_DEF ? '[<a href="'.PHP_SELF.'?res='.$resno.'&amp;page_num=all">'._T('all_pages').'</a>] ' : '');
				}
				$pte_vals['{$PAGENAV}'] .= '</td><td style="white-space: nowrap;">';
				$pte_vals['{$PAGENAV}'] .= (!$AllRes && $tree_count > $next * RE_PAGE_DEF) ? '<a href="'.PHP_SELF.'?res='.$resno.'&amp;page_num='.$next.'">'._T('next_page').'</a>' : _T('last_page');
				$pte_vals['{$PAGENAV}'] .= '</td></tr></table>'."\n";
			}
		}else{ // 一般分頁
			$pte_vals['{$PAGENAV}'] .= '<table class="pages" border="1"><tr>';
			if($prev >= 0){
				if(!$adminMode && $prev==0) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF2.'" method="get">';
				else{
					if($adminMode || (STATIC_HTML_UNTIL != -1) && ($prev > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF.'?page_num='.$prev.'" method="post">';
					else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$prev.PHP_EXT.'" method="get">';
				}
				$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('prev_page').'" /></div></form></td>';
			}else $pte_vals['{$PAGENAV}'] .= '<td style="white-space: nowrap;">'._T('first_page').'</td>';
			$pte_vals['{$PAGENAV}'] .= '<td>';
			for($i = 0, $len = $threads_count / PAGE_DEF; $i < $len; $i++){
				if($page==$i) $pte_vals['{$PAGENAV}'] .= "[<b>".$i."</b>] ";
				else{
					$pageNext = ($i==$next) ? ' rel="next"' : '';
					if(!$adminMode && $i==0) $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF2.'?">0</a>] ';
					elseif($adminMode || (STATIC_HTML_UNTIL != -1 && $i > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF.'?page_num='.$i.'"'.$pageNext.'>'.$i.'</a>] ';
					else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$i.PHP_EXT.'?"'.$pageNext.'>'.$i.'</a>] ';
				}
			}
			$pte_vals['{$PAGENAV}'] .= '</td>';
			if($threads_count > $next * PAGE_DEF){
				if($adminMode || (STATIC_HTML_UNTIL != -1) && ($next > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF.'?page_num='.$next.'" method="post">';
				else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$next.PHP_EXT.'" method="get">';
				$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('next_page').'" /></div></form></td>';
			}else $pte_vals['{$PAGENAV}'] .= '<td style="white-space: nowrap;">'._T('last_page').'</td>';
			$pte_vals['{$PAGENAV}'] .= '</tr></table>'."\n";
		}
		$pte_vals['{$PAGENAV}'] .= '<br style="clear: left;" />
</div>';
		$dat .= $PTE->ParseBlock('MAIN', $pte_vals);
		foot($dat); // the foot is a special core function wwww --sparky4

		// 存檔 / 輸出
		if($single_page || ($page_num == -1 && !$resno)){ // 靜態快取頁面生成
			if($page==0) $logfilename = PHP_SELF2;
			else $logfilename = $page.PHP_EXT;
			$fp = fopen($logfilename, 'w');
			stream_set_write_buffer($fp, 0);
			fwrite($fp, $dat);
			fclose($fp);
			@chmod($logfilename, 0666);
			if(STATIC_HTML_UNTIL != -1 && STATIC_HTML_UNTIL==$page) break; // 頁面數目限制
		}else{ // PHP 輸出 (回應模式/一般動態輸出)
			if(USE_RE_CACHE && !$adminMode && $resno && !isset($_GET['upseries'])){ // 更新快取
				if($oldCaches = glob($cacheFile.'*')){
					foreach($oldCaches as $o) unlink($o); // 刪除舊快取
				}
				$fp = fopen($cacheGzipPrefix.$cacheFile.$cacheETag, 'w');
				fwrite($fp, $dat);
				fclose($fp);
			
      @chmod($cacheFile.$cacheETag, 0666);
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
			}
			echo $dat;
			break;
		}
	}
}

/* 輸出討論串架構 */
function arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno=0, $arr_kill, $arr_old, $kill_sensor, $old_sensor, $showquotelink=true, $adminMode=false, $hiddenImage, $hiddenImagP, $hiddenImagRP, $posts_img){
	global $config, $PIO, $FileIO, $PMS, $language;

	$thdat = ''; // 討論串輸出碼
	$posts_count = count($posts); // 迴圈次數
	if($hiddenReply) $posts_img_count = count($posts_img); // o++
	if(gettype($tree_cut) == 'array') $tree_cut = array_flip($tree_cut); // array_flip + isset 搜尋法
	if(gettype($tree) == 'array') $tree_clone = array_flip($tree);
	if($hiddenReply){ // o++
		// $o = 0 (首篇), $o = 1～n (回應) //++++----
		for($o = 0; $o < $posts_img_count; $o++){ // o++
			extract($posts_img[$o]); // o++
			if($ext && $FileIO->imageExists($tim.$ext)) $hiddenImage++; // o++ all images in thread
		}
		// $oo = 0 (首篇), $oo = 1～n (回應) //++++----
		for($oo = 0; $oo < $posts_count; $oo++){ // o++
			extract($posts[$oo]); // o++
			if($oo) if($ext && $FileIO->imageExists($tim.$ext)) $hiddenImagP++; // o++ reply images in index
			elseif(!$ext && !$FileIO->imageExists($tim.$ext)) $hiddenImagRP = 0; // o++ opening thread image in index
		}

		// A little math is done to get the total number of omitted images
		$hiddenImgs = ($hiddenImage - $hiddenImagP) - $hiddenImagRP; // o++
	}

	// Actually beginning the REAL post extraction
	// $i = 0 (首篇), $i = 1～n (回應)
	for($i = 0; $i < $posts_count; $i++){
		$imgsrc = $img_thumb = $imgwh_bar = $imgfn_bar = '';
		$IMG_BAR = $REPLYBTN = $QUOTEBTN = $WARN_OLD = $WARN_BEKILL = $WARN_ENDREPLY = $WARN_HIDEPOST = '';
		extract($posts[$i]); // 取出討論串文章內容設定變數

		if($no == 0) break; // Do not display post 0

		// 設定欄位值
		$name = str_replace('&'.TRIP_KEY, '&amp;'.TRIP_KEY, $name); // 避免 &#xxxx; 後面被視為 Trip 留下 & 造成解析錯誤
		if(CLEAR_SAGE) $email = preg_replace('/^sage( *)/i', '', trim($email)); // 清除E-mail中的「sage」關鍵字
		// Tripcode indicator // t++ //++++----
		// This may be a blob of code but it apparently works >< please help me here
		$tripkeycount = substr_count($name, TRIP_KEY);
		if($config['ALLOW_NONAME']==3){
			if($name || $tripkeycount) $name = $config['DEFAULT_NONAME'];
			if($email) $now = "<a href=\"mailto:$email\" class=\"linkmail\">$now</a>";
		}else
		if($config['ALLOW_NONAME']==2){ // 強制砍名
			if($tripkeycount==1) $name = preg_match('/(\\'.TRIP_KEY.'.{10})/', $name, $matches) ? '<span class="postertrip">'.$matches[1].'</span>' : '';
			if($tripkeycount==2) $name = preg_match('/(\\'.TRIP_KEY.TRIP_KEY.'.{16})/', $name, $matches) ? '<span class="postertrip">'.$matches[1].'</span>' : '';
			if($tripkeycount==3) $name = preg_match('/(\\'.TRIP_KEY.'.{32})/', $name, $matches) ? '<span class="postertrip">'.$matches[1].'</span>' : '';
			if($email) $now = "<a href=\"mailto:$email\" class=\"linkmail\">$now</a>";
		}else{
			if($tripkeycount==1) $name = preg_replace('/(\\'.TRIP_KEY.'.{10})/', '<span class="postertrip">$1</span>', $name); // Trip取消粗體
			if($tripkeycount==2) $name = preg_replace('/(\\'.TRIP_KEY.TRIP_KEY.'.{16})/', '<span class="postertrip">$1</span>', $name); // Trip取消粗體
			if($tripkeycount==3) $name = preg_replace('/(\\'.TRIP_KEY.'.{32})/', '<span class="postertrip">$1</span>', $name); // Trip取消粗體
			if($email) $name = "<a href=\"mailto:$email\" class=\"linkmail\">$name</a>";
		}

		if(AUTO_LINK) $com = auto_link($com);
		$com = quoteLight($com);
		$com = quoteLight2($com);
		if(!$resno && $config['LINE_NUMBER']) list($com, $abbreviated) = abbreviate($com, $config['LINE_NUMBER']);
		if(isset($abbreviated) && $abbreviated && $config['LINE_NUMBER']) $com .= '<br /><span class="abbrev">'._T('long_comment').'<a href="'.PHP_SELF.'?res='.$tree[0].'#r'.$no.'">'._T('long_here').'</a>'._T('long_see').'</span>';

		if(USE_QUOTESYSTEM && $i){ // 啟用引用瀏覽系統
			if(preg_match_all('/((?:&gt;|＞)+)(?:No\.)?(\d+)/i', $com, $matches, PREG_SET_ORDER)){ // 找尋>>No.xxx
				$matches_unique = array();
				foreach($matches as $val){ if(!in_array($val, $matches_unique)) array_push($matches_unique, $val); }
				foreach($matches_unique as $val){
					if(isset($tree_clone[$val[2]])){
						$r_page = $tree_clone[$val[2]]; // 引用回應在整體討論串中的位置
						// 在此頁顯示區間內，輸出錨點即可
						if(isset($tree_cut[$val[2]])) $com = str_replace($val[0], '<span class="reflink"><a class="qlink" href="#r'.$val[2].'" onclick="replyhl('.$val[2].');">'.$val[0].'</a></span>', $com);
						// 非此頁顯示區間，輸出完整頁面位置
						else $com = str_replace($val[0], '<span class="reflink"><a class="qlink" href="'.PHP_SELF.'?res='.$tree[0].(RE_PAGE_DEF ? '&amp;page_num='.floor(($r_page - 1) / RE_PAGE_DEF) : '').'#r'.$val[2].'">'.$val[0].'</a></span>', $com);
					}
				}
			}
		}

		// 設定附加圖檔顯示
		if($ext && !$FileIO->imageExists($tim.$ext)){
			$imgsrc = '<img src="'.ICON_DIR.'filedeleted.gif" class="img" alt="'.$imgsize.'" title="'.$imgsize.'" />';
		}elseif($ext && $FileIO->imageExists($tim.$ext)){
			$imageURL = $FileIO->getImageURL($tim.$ext); // image URL
			$thumbURL = $FileIO->getImageURL($tim.'s.jpg'); // thumb URL

			//----$imgsrc = '<a href="'.$imageURL.'" rel="_blank"><img src="'.ICON_DIR.'nothumb.gif" class="img" alt="'.$imgsize.'" title="'.$imgsize.'" /></a>'; // 預設顯示圖樣式 (無預覽圖時)
			$imgsrc = '<a href="'.$imageURL.'" rel="_blank"><span class="tn_reply" title="'.$imgsize.'">'._T('nothumb').'</span></a>'; // 預設顯示圖樣式 (無預覽圖時)
			if($tw && $th){
				if($FileIO->imageExists($tim.'s.jpg')){ // 有預覽圖
					$img_thumb = '<br /><small><span class="thumbnailmsg">'._T('img_sample').'</span></small>';
					$imgsrc = '<a href="'.$imageURL.'" rel="_blank"><img src="'.$thumbURL.'" style="width: '.$tw.'px; height: '.$th.'px;" class="img" alt="'.$imgsize.'" title="'.$imgsize.'" /></a>';
				}elseif($ext=='.swf') $imgsrc = ''; // swf檔案不需預覽圖
			}
			if(SHOW_IMGWH) $imgwh_bar = ', '.$imgw.'x'.$imgh; // 顯示附加圖檔之原檔長寬尺寸
			if(SHOW_FILENAME){
				$longname = $shortname = '';
				if($filename){
					$longname = $filename.$ext;
					if(strlen($filename) > 40) $shortname = substr($filename, 0, 40).'(....)'.$ext;
					else $shortname = $longname;
				}
			}
			if($longname) $imgfn_bar = ', <span title="'.$longname.'">'.$shortname.'</span>';
			$IMG_BAR = '<span class="filesize">'._T('img_filename').'<a href="'.$imageURL.'" rel="_blank">'.$tim.$ext.'</a>-('.$imgsize.$imgwh_bar.$imgfn_bar.')</span>'.$img_thumb;
		}

		// 設定回應 / 引用連結
		if($resno){ // 回應模式
			if($showquotelink) $QUOTEBTN = '<span class="reflink"><a href="'.PHP_SELF.'?res='.$tree[0].'#r'.$no.'" onclick="return replyhl('.$no.');">No.</a><a href="javascript:quote('.$no.');" class="qlink">'.$no.'</a></span>';
			else $QUOTEBTN = '<span class="reflink"><a href="'.PHP_SELF.'?res='.$tree[0].'#r'.$no.'" onclick="return replyhl('.$no.');">No.</a><a href="'.PHP_SELF.'?res='.$tree.'&amp;page_num=all#r'.$no.'" class="qlink">'.$no.'</a></span>';
		}else{
			if(!$i)	$REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">'._T('reply_btn').'</a>]'; // 首篇
			$QUOTEBTN = '<span class="reflink"><a href="'.PHP_SELF.'?res='.$tree[0].'#r'.$no.'">No.</a><a href="'.PHP_SELF.'?res='.$tree[0].'#q'.$no.'" class="qlink">'.$no.'</a></span>';
		}
		if($adminMode){ // 前端管理模式
			$modFunc = '';
			$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$i], $resto)); // "AdminList" Hook Point
			$QUOTEBTN .= $modFunc;
		}

		// 設定討論串屬性
		if(STORAGE_LIMIT && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<span class="warn_txt">'._T('warn_sizelimit').'</span><br />'."\n"; // 預測刪除過大檔
		if(!$i){ // 首篇 Only
			if($old_sensor) if(isset($arr_old[$no])) $WARN_OLD = '<span class="oldpost">'._T('warn_oldthread').'</span><br />'."\n"; // 快要被刪除的提示
			$flgh = $PIO->getPostStatus($status);
			if($flgh->exists('TS')) $WARN_ENDREPLY = '<span class="warn_txt">'._T('warn_locked').'</span><br />'."\n"; // 被標記為禁止回應
			if($hiddenReply) $WARN_HIDEPOST = '<span class="omittedposts">'._res($hiddenReply, $hiddenImgs)._T('notice_omitted_reply').'</span><br />'."\n"; // 有隱藏的回應
		}

		// 對類別標籤作自動連結
		if(USE_CATEGORY){
			$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
			$ary_category_count = count($ary_category);
			$ary_category2 = array();
			for($p = 0; $p < $ary_category_count; $p++){
				if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&amp;c='.urlencode($c).'">'.$c.'</a>';
			}
			$category = implode(', ', $ary_category2);
		}else $category = '';

		// 最終輸出處
		if($i){ // 回應
			$arrLabels = array('{$NO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$QUOTEBTN}'=>$QUOTEBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$QUOTEBTN}'=>$QUOTEBTN, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$SELF}'=>PHP_SELF);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadReply', array(&$arrLabels, $posts[$i], $resno)); // "ThreadReply" Hook Point
			$thdat .= $PTE->ParseBlock('REPLY',$arrLabels);
		}else{ // 首篇
			$arrLabels = array('{$NO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$QUOTEBTN}'=>$QUOTEBTN, '{$REPLYBTN}'=>$REPLYBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_OLD}'=>$WARN_OLD, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$WARN_ENDREPLY}'=>$WARN_ENDREPLY, '{$WARN_HIDEPOST}'=>$WARN_HIDEPOST, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$SELF}'=>PHP_SELF);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadPost', array(&$arrLabels, $posts[$i], $resno)); // "ThreadPost" Hook Point
			$thdat .= $PTE->ParseBlock('THREAD',$arrLabels);
		}
	}
	$thdat .= $PTE->ParseBlock('THREADSEPARATE',($resno)?array('{$RESTO}'=>$resno):array());
	return $thdat;
}

/* 寫入記錄檔 */
/* 記事書き込み */
function regist(){
	global $config, $PIO, $FileIO, $PMS, $language, $BAD_STRING, $BAD_FILEMD5, $BAD_IPADDR, $LIMIT_SENSOR;
	$dest = ''; $mes = ''; $up_incomplete = 0; $is_admin = false;
	$path = realpath('.').DIRECTORY_SEPARATOR; // 此目錄的絕對位置

	if($_SERVER['REQUEST_METHOD'] != 'POST') error(_T('regist_notpost')); // 非正規POST方式
	// 欄位陷阱
	$FTname = isset($_POST['name']) ? $_POST['name'] : '';
	$FTemail = isset($_POST['email']) ? $_POST['email'] : '';
	$FTsub = isset($_POST['sub']) ? $_POST['sub'] : '';
	$FTcom = isset($_POST['com']) ? $_POST['com'] : '';
	$FTreply = isset($_POST['reply']) ? $_POST['reply'] : '';
	if($FTname != 'spammer' || $FTemail != 'foo@foo.bar' || $FTsub != 'DO NOT FIX THIS' || $FTcom != 'EID OG SMAPS' || $FTreply != '') error(_T('regist_nospam'));

	$name = isset($_POST[FT_NAME]) ? CleanStr($_POST[FT_NAME]) : '';
	$email = isset($_POST[FT_EMAIL]) ? CleanStr($_POST[FT_EMAIL]) : '';
	$sub = isset($_POST[FT_SUBJECT]) ? CleanStr($_POST[FT_SUBJECT]) : '';
	$com = isset($_POST[FT_COMMENT]) ? $_POST[FT_COMMENT] : '';
	$pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
	$category = isset($_POST['category']) ? CleanStr($_POST['category']) : '';
	$resto = isset($_POST['resto']) ? intval($_POST['resto']) : 0;
	$upfile = isset($_FILES['upfile']['tmp_name']) ? $_FILES['upfile']['tmp_name'] : '';
	$upfile_path = isset($_POST['upfile_path']) ? $_POST['upfile_path'] : '';
	$upfile_name = isset($_FILES['upfile']['name']) ? $_FILES['upfile']['name'] : false;
	$ext_ = ereg_replace("^.*\\.", ".", $upfile_name); // filename extention
	$basename = basename($upfile_name, $ext_); // filename
	$filename = isset($basename) ? CleanStr($basename) : ''; // cleaned filename
	$upfile_status = isset($_FILES['upfile']['error']) ? $_FILES['upfile']['error'] : 4;
	$pwdc = isset($_COOKIE['pwdc']) ? $_COOKIE['pwdc'] : '';
	$ip = getREMOTE_ADDR(); $host = gethostbyaddr($ip);

	$PMS->useModuleMethods('RegistBegin', array(&$name, &$email, &$sub, &$com, array('file'=>&$upfile, 'path'=>&$upfile_path, 'name'=>&$upfile_name, 'status'=>&$upfile_status), array('ip'=>$ip, 'host'=>$host), $resto)); // "RegistBegin" Hook Point
	// 封鎖：IP/Hostname/DNSBL 檢查機能
	$baninfo = '';
	if(BanIPHostDNSBLCheck($ip, $host, $baninfo)) error(_T('regist_ipfiltered', $baninfo));
	// 封鎖：限制出現之文字
	foreach($BAD_STRING as $value){
		if(strpos($com, $value)!==false || strpos($sub, $value)!==false || strpos($name, $value)!==false || strpos($email, $value)!==false){
			error(_T('regist_wordfiltered'));
		}
	}

	// 檢查是否輸入櫻花日文假名
	foreach(array($name, $email, $sub, $com) as $anti) if(anti_sakura($anti)) error(_T('regist_sakuradetected'));

	// 時間
	$time = time();
	$tim = $time.substr(microtime(),2,3);
	$tome = time()+0*60*60;

	// 判斷上傳狀態
	switch($upfile_status){
		case 1:
			error(_T('regist_upload_exceedphp'));
			break;
		case 2:
			error(_T('regist_upload_exceedcustom'));
			break;
		case 3:
			error(_T('regist_upload_incompelete'));
			break;
		case 6:
			error(_T('regist_upload_direrror'));
			break;
		case 4: // 無上傳
			if(NO_TEXTONLY == 2){
				if(!$resto) error(_T('regist_upload_noimg'));
			}elseif(NO_TEXTONLY == 1){
				if(!$resto && !isset($_POST['noimg'])) error(_T('regist_upload_noimg'));
			}
			break;
		case 0: // 上傳正常
		default:
	}

	// 如果有上傳檔案則處理附加圖檔
	if($upfile && (@is_uploaded_file($upfile) || @is_file($upfile))){
		// 一‧先儲存檔案
		$dest = $path.TEMP_DIR.$tim.'.temp';
		@move_uploaded_file($upfile, $dest) or @copy($upfile, $dest);
		@chmod($dest, 0666);
		if(!is_file($dest)) error(_T('regist_upload_filenotfound'), $dest);

		// 二‧判斷上傳附加圖檔途中是否有中斷
		$upsizeTTL = $_SERVER['CONTENT_LENGTH'];
		if(isset($_FILES['upfile'])){ // 有傳輸資料才需要計算，避免作白工
			$upsizeHDR = 0;
			// 檔案路徑：IE附完整路徑，故得從隱藏表單取得
			$tmp_upfile_path = $upfile_name;
			if($upfile_path) $tmp_upfile_path = get_magic_quotes_gpc() ? stripslashes($upfile_path) : $upfile_path;
			list(,$boundary) = explode('=', $_SERVER['CONTENT_TYPE']);
			foreach($_POST as $header => $value){ // 表單欄位傳送資料
				$upsizeHDR += strlen('--'.$boundary."\r\n");
				$upsizeHDR += strlen('Content-Disposition: form-data; name="'.$header.'"'."\r\n\r\n".(get_magic_quotes_gpc()?stripslashes($value):$value)."\r\n");
			}
			// 附加圖檔欄位傳送資料
			$upsizeHDR += strlen('--'.$boundary."\r\n");
			$upsizeHDR += strlen('Content-Disposition: form-data; name="upfile"; filename="'.$tmp_upfile_path."\"\r\n".'Content-Type: '.$_FILES['upfile']['type']."\r\n\r\n");
			$upsizeHDR += strlen("\r\n--".$boundary."--\r\n");
			$upsizeHDR += $_FILES['upfile']['size']; // 傳送附加圖檔資料量
			// 上傳位元組差值超過 HTTP_UPLOAD_DIFF：上傳附加圖檔不完全
			if(($upsizeTTL - $upsizeHDR) > HTTP_UPLOAD_DIFF){
				if(KILL_INCOMPLETE_UPLOAD){
					unlink($dest);
					die(_T('regist_upload_killincomp')); // 給瀏覽器的提示，假如使用者還看的到的話才不會納悶
				}else $up_incomplete = 1;
			}
		}

		// 三‧檢查是否為可接受的檔案
		$size = @getimagesize($dest);
		if(!is_array($size)) error(_T('regist_upload_notimage'), $dest); // $size不為陣列就不是圖檔
		$imgsize = @filesize($dest); // 檔案大小
		if(!$config['KB']) $imgsize .= ' B'; // Bytes only
		else $imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // KB和B的判別
		switch($size[2]){ // 判斷上傳附加圖檔之格式
			case 1 : $ext = ".gif"; break;
			case 2 : $ext = ".jpg"; break;
			case 3 : $ext = ".png"; break;
			case 4 : $ext = ".swf"; break;
			case 5 : $ext = ".psd"; break;
			case 6 : $ext = ".bmp"; break;
			case 7 : $ext = ".tiff"; break;
			case 8 : $ext = ".tiff"; break;
			case 9 : $ext = ".jpc"; break;
			case 10 : $ext = ".jp2"; break;
			case 11 : $ext = ".jpx"; break;
			case 12 : $ext = ".jb2"; break;
			case 13 : $ext = ".swf"; break;
			case 14 : $ext = ".aiff"; break;
			case 15 : $ext = ".wbmp"; break;
			case 16 : $ext = ".xbm"; break; // I add more media support (^^,)
			default : $ext = ".xxxx"; error(_T('regist_upload_notsupport'), $dest);
		}
		$allow_exts = explode('|', strtolower(ALLOW_UPLOAD_EXT)); // 接受之附加圖檔副檔名
		if(array_search(substr($ext, 1), $allow_exts)===false) error(_T('regist_upload_notsupport'), $dest); // 並無在接受副檔名之列
		// 封鎖設定：限制上傳附加圖檔之MD5檢查碼
		$md5chksum = md5_file($dest); // 檔案MD5
		if(array_search($md5chksum, $BAD_FILEMD5)!==FALSE) error(_T('regist_upload_blocked'), $dest); // 在封鎖設定內則阻擋

		// 四‧計算附加圖檔圖檔縮圖顯示尺寸 //++++---- <- the comment from pixmicat team is broken here 
		$W = $imgW = $size[0];
		$H = $imgH = $size[1];
		$MAXW = $resto ? MAX_RW : MAX_W;
		$MAXH = $resto ? MAX_RH : MAX_H;
		if($W > $MAXW || $H > $MAXH){
			$W2 = $MAXW / $W;
			$H2 = $MAXH / $H;
			$key = ($W2 < $H2) ? $W2 : $H2;
			$W = ceil($W * $key);
			$H = ceil($H * $key);
		}
		$mes = _T('regist_uploaded', CleanStr($upfile_name));
	}

	// 檢查表單欄位內容並修整
	if(strlen($name) > 100) error(_T('regist_nametoolong'), $dest);
	if(strlen($email) > 100) error(_T('regist_emailtoolong'), $dest);
	if(strlen($sub) > 100) error(_T('regist_topictoolong'), $dest);
	if(strlen($resto) > 10) error(_T('regist_longthreadnum'), $dest);

	// E-mail / 標題修整
	$email = str_replace("\r\n", '', $email); $sub = str_replace("\r\n", '', $sub);

	// Tripcode area--------------------------------------------------------------------------------
	if($name){
		// 名稱修整
		$name = str_replace(TRIP_KEY, TRIP_KEY_FAKE, $name); // 防止トリップ偽造
		$name = str_replace($config['CAP.SUFFIX'], $config['CAP.SUFFIX.FAKE'], $name); // 防止管理員キャップ偽造
		$name = str_replace("\r\n", '', $name);
		$nameOri = $name; // 名稱
		// トリップ
		//----if(preg_match('/(.*?)[#＃](.*)/u', $name, $regs)){ // トリップ(Trip)機能
			//----$name = $nameOri = $regs[1]; $cap = strtr($regs[2], array('&amp;'=>'&'));
			//----$salt = preg_replace('/[^\.-z]/', '.', substr($cap.'H.', 1, 2));
			//----$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
			//----$name = $name.TRIP_KEY.substr(crypt($cap, $salt), -10);
		if(preg_match('/(.*?)[#＃](.*)/u', $name, $regs)){ // トリップ(Trip)機能
			$name = str_replace("&#", "&%%%%%%", $name); # otherwise HTML numeric entities screw up explode()!
			list($name, $trip, $sectrip) = str_replace("&&", "&%%%%%%", explode("#", $name));

			if($trip != ''){
				if(function_exists("iconv")) $trip = iconv("UTF-8", "SHIFT_JIS//TRANSLIT", $trip); // convert to Windows Japanese #&#65355;&#65345;&#65357;&#65353;
				$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip.'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
				$metrip = TRIP_KEY.substr(crypt($trip, $salt), -10);
			}else $metrip = '';
			if($sectrip != ''){
				if(function_exists("iconv")) $sectrip = iconv("UTF-8", "SHIFT_JIS//TRANSLIT", $sectrip); // convert to Windows Japanese #&#65355;&#65345;&#65357;&#65353;
				$salt = "LOLLOLOLOLOLOLOLOLOLOLOLOLOLOLOLMEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE"; # this is ONLY used if the host doesn't have openssl # I don't know a better way to get random data
				if(file_exists(SALTFILE)){ # already generated a key
					$salt = file_get_contents(SALTFILE);
				}else{
					system("openssl rand 448 > '".SALTFILE, $err);
					if($err === 0){
						if(chmod(SALTFILE,0400)){
							$salt = file_get_contents(SALTFILE);
						}else{
							$donk = IDSEED;
							$fp = fopen(SALTFILE, "w");
							fputs($fp, $donk);
							fclose($fp);
						}
					}
				}
				$sha = base64_encode(pack("H*", sha1($sectrip.$salt)));
				$sha = substr($sha, 0, 16);
				$metrip .= TRIP_KEY.TRIP_KEY.$sha;
			}
			// Tripcode filter
			//++++if(file_exists(FILTER_DIR)){
				//++++include(FILTER_DIR.'trip.php');
			//++++}
			//----"</b></font><font class=\"postertrip\">".
			$name .= $metrip;
		}
		if($config['CAP.ENABLE'] && preg_match('/(.*?)[#＃](.*)/u', $email, $aregs)){ // 管理員キャップ(Cap)機能
			$acap_name = $nameOri; $acap_pwd = strtr($aregs[2], array('&amp;'=>'&'));
			if($acap_name==$config['CAP.NAME'] && $acap_pwd==$config['CAP.PASSWORD']){
				$name = '<span class="admin_cap">'.$name.$config['CAP.SUFFIX'].'</span>';
				$is_admin = true;
				$email = $aregs[1]; // 去除 #xx 密碼
			}
		}
		if(!$is_admin){ // 非管理員
			$name = str_replace(_T('admin'), '"'._T('admin').'"', $name);
			$name = str_replace(_T('deletor'), '"'._T('deletor').'"', $name);
		}
		$name = str_replace('&'.TRIP_KEY, '&amp;'.TRIP_KEY, $name); // 避免 &#xxxx; 後面被視為 Trip 留下 & 造成解析錯誤
	}
	// End of Tripcode area--------------------------------------------------------------------------

	// 內文修整
	if((strlen($com) > COMM_MAX) && !$is_admin) error(_T('regist_commenttoolong'), $dest);
	$com = CleanStr($com, $is_admin); // 引入$is_admin參數是因為當管理員キャップ啟動時，允許管理員依config設定是否使用HTML
	if(!$com && $upfile_status==4) error(_T('regist_withoutcomment'));
	$com = str_replace(array("\r\n", "\r"), "\n", $com); $com = ereg_replace("\n((　| )*\n){4,}", "\n", $com);
	if(!BR_CHECK || substr_count($com,"\n") < BR_CHECK) $com = nl2br($com); // 換行字元用<br />代替
	$com = str_replace("\n", '', $com); // 若還有\n換行字元則取消換行
	// 預設的內容
	if(!$name || ereg("^[ |　|]*$", $name)){
		if($config['ALLOW_NONAME']) $name = $config['DEFAULT_NONAME'];
		else error(_T('regist_withoutname'), $dest);
	}
	if(!$sub || ereg("^[ |　|]*$", $sub)){
		if($config['ALLOW_NOSUB']) $sub = $config['DEFAULT_NOTITLE'];
		else error(_T('regist_withoutsubject'), $dest);
	}
	if(!$com || ereg("^[ |　|\t]*$", $com)){
		if($config['ALLOW_NOCOM']) $com = $config['DEFAULT_NOCOMMENT'];
		else error(_T('regist_withoutcomment'), $dest);
	}
	// 修整標籤樣式
	if($category && USE_CATEGORY){
		$category = explode(',', $category); // 把標籤拆成陣列
		$category = ','.implode(',', array_map('trim', $category)).','; // 去空白再合併為單一字串 (左右含,便可以直接以,XX,形式搜尋)
	}else{ $category = ''; }
	if($up_incomplete) $com .= '<br /><br /><span class="warn_txt">'._T('notice_incompletefile').'</span>'; // 上傳附加圖檔不完全的提示

	// 密碼和時間的樣式 
	if($pwd=='') if($config['ALLOW_NOPASS']) error(_T('regist_withoutpassword'), $dest); else $pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
	$pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // 生成真正儲存判斷用的密碼
	$youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
//----	$yd = $youbi[gmdate('w', $time+TIME_ZONE*60*60)];
	$yd = $youbi[date('w', $tome)];
//----	$now = gmdate('y/m/d', $time+TIME_ZONE*60*60).'('.(string)$yd.')'.gmdate('H:i', $time+TIME_ZONE*60*60);
	$now = date($config['DATE_FORMAT'], $tome).'('.(string)$yd.')'.date($config['TIME_FORMAT'], $tome);

//----	if(DISP_ID){ // 顯示ID
//----		if($email && DISP_ID==1) $now .= ' ID:????';
//----		else $now .= ' ID:'.substr(crypt(md5(getREMOTE_ADDR().IDSEED.gmdate('Ymd', $time+TIME_ZONE*60*60)),'id'), -8);
//----	}
	if(DISP_ID){ // 顯示ID
		if($email&&DISP_ID==1) $now .= " ID:????";
		else $now .= " ID:".substr(crypt(md5(getREMOTE_ADDR().IDSEED.date($config['DATE_FORMAT'], $tome)),'id'), -8);
	}

	// 連續投稿 / 相同附加圖檔檢查
	$checkcount = 50; // 預設檢查50筆資料
	$pwdc = substr(md5($pwdc), 2, 8); // Cookies密碼
	if($PIO->isSuccessivePost($checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name)) error(_T('regist_successivepost'), $dest); // 連續投稿檢查
	if($dest){ if($PIO->isDuplicateAttachment($checkcount, $md5chksum)) error(_T('regist_duplicatefile'), $dest); } // 相同附加圖檔檢查
	if($resto) $ThreadExistsBefore = $PIO->isThread($resto);

	// 舊文章刪除處理
	if(PIOSensor::check('delete', $LIMIT_SENSOR)){
		$delarr = PIOSensor::listee('delete', $LIMIT_SENSOR);
		if(count($delarr)){
			deleteCache($delarr);
			$PMS->useModuleMethods('PostOnDeletion', array($delarr, 'recycle')); // "PostOnDeletion" Hook Point
			$files = $PIO->removePosts($delarr);
			if(count($files)) $FileIO->deleteImage($files);
		}
	}

	// 附加圖檔容量限制功能啟動：刪除過大檔
	if(STORAGE_LIMIT && STORAGE_MAX > 0){
		$tmp_total_size = total_size(); // 取得目前附加圖檔使用量
		if($tmp_total_size > STORAGE_MAX){
			$files = $PIO->delOldAttachments($tmp_total_size, STORAGE_MAX, false);
			$FileIO->deleteImage($files);
		}
	}

	// 判斷欲回應的文章是不是剛剛被刪掉了
	if($resto){
		if($ThreadExistsBefore){ // 欲回應的討論串是否存在
			if(!$PIO->isThread($resto)){ // 被回應的討論串存在但已被刪
				// 提前更新資料來源，此筆新增亦不紀錄
				$PIO->dbCommit();
				updatelog();
				error(_T('regist_threaddeleted'), $dest);
			}else{ // 檢查是否討論串被設為禁止回應 (順便取出原討論串的貼文時間)
				$post = $PIO->fetchPosts($resto); // [特殊] 取單篇文章內容，但是回傳的$post同樣靠[$i]切換文章！
				list($chkstatus, $chktime) = array($post[0]['status'], $post[0]['tim']);
				$chktime = substr($chktime, 0, -3); // 拿掉微秒 (後面三個字元)
				$flgh = $PIO->getPostStatus($chkstatus);
				if($flgh->exists('TS')) error(_T('regist_threadlocked'), $dest);
			}
		}else error(_T('thread_not_found'), $dest); // 不存在
	}

	// 計算某些欄位值
	$no = $PIO->getLastPostNo('beforeCommit') + 1;
	isset($ext) ? 0 : $ext = '';
	isset($imgW) ? 0 : $imgW = 0;
	isset($imgH) ? 0 : $imgH = 0;
	isset($imgsize) ? 0 : $imgsize = '';
	isset($W) ? 0 : $W = 0;
	isset($H) ? 0 : $H = 0;
	isset($md5chksum) ? 0 : $md5chksum = '';
	$age = false;
	$status = '';
	if(USE_UPSERIES && stristr($email, 'noko')){
		$email = false;
		$noko = true;
	}else $noko = false;
	if($resto){
		if(!stristr($email, 'sage') && ($PIO->postCount($resto) <= MAX_RES || MAX_RES==0)){
			if(!MAX_AGE_TIME || (($time - $chktime) < (MAX_AGE_TIME * 60 * 60))) $age = true; // 討論串並無過期，推文
		}
	}
	$PMS->useModuleMethods('RegistBeforeCommit', array(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, array($W, $H, $imgW, $imgH), &$status)); // "RegistBeforeCommit" Hook Point

	// 正式寫入儲存
	$PIO->addPost($no,$resto,$md5chksum,$category,$tim,$ext,$imgW,$imgH,$imgsize,$filename,$W,$H,$pass,$now,$name,$email,$sub,$com,$host,$age,$status);
//----	$PIO->addPost($no,$now,$name,$email,$sub,$com,$age,$status,$host,$pwd,$ext,$W,$H,$tim,$md5chksum,$imgsize,$filename,$imgW,$imgH,$category,$resto);
	$PIO->dbCommit();
	$lastno = $PIO->getLastPostNo('afterCommit'); // 取得此新文章編號
	$PMS->useModuleMethods('RegistAfterCommit', array($lastno, $resto, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point

	// noko in cookie
	if($noko) $email = 'noko';

	// Cookies儲存：密碼與E-mail部分，期限是一週
	setcookie('pwdc', $pwd, time()+7*24*3600, '/');
	setcookie('emailc', $email, time()+7*24*3600, '/');
	total_size(true); // 刪除舊容量快取
	if($dest && is_file($dest)){
		$destFile = $path.IMG_DIR.$tim.$ext; // 圖檔儲存位置
		$thumbFile = $path.THUMB_DIR.$tim.'s.jpg'; // 預覽圖儲存位置
		rename($dest, $destFile);
		if(USE_THUMB !== 0){ // 生成預覽圖
			$thumbType = USE_THUMB; if(USE_THUMB==1){ $thumbType = 'gd'; } // 與舊設定相容
			require(PHP_DIRECTORY.'lib/thumb/thumb.'.$thumbType.'.php');
			$thObj = new ThumbWrapper($destFile, $imgW, $imgH);
			$thObj->setThumbnailConfig($W, $H, THUMB_Q);
			$thObj->makeThumbnailtoFile($thumbFile);
			@chmod($thumbFile, 666);
			unset($thObj);
		}
		if($FileIO->uploadImage()){ // 支援上傳圖片至其他伺服器
			if(file_exists($destFile)) $FileIO->uploadImage($tim.$ext, $destFile, filesize($destFile));
			if(file_exists($thumbFile)) $FileIO->uploadImage($tim.'s.jpg', $thumbFile, filesize($thumbFile));
		}
	}
	updatelog();

	// 引導使用者至新頁面
	$RedirURL = PHP_SELF2.'?'.$tim; // 定義儲存資料後轉址目標
	if(isset($_POST['up_series']) || $noko){ // 勾選連貼機能
		if($resto) $RedirURL = PHP_SELF.'?res='.$resto.'&amp;upseries=1'; // 回應後繼續轉回此主題下
		else{
			$RedirURL = PHP_SELF.'?res='.$lastno.'&amp;upseries=1'; // 新增主題後繼續轉到此主題下
		}
	}
	$RedirforJS = strtr($RedirURL, array("&amp;"=>"&")); // JavaScript用轉址目標

	echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
	echo <<< _REDIR_
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.PIXMICAT_LANGUAGE.'">
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Refresh" content="1;URL=$RedirURL" />
<script type="text/javascript">
// Redirection (use JS)
// <![CDATA[
function redir(){
	location.href = "$RedirforJS";
}
setTimeout("redir()", 1000);
// ]]>
</script>
</head>
<body>
<div>
_REDIR_;
echo _T('regist_redirect',$mes,$RedirURL).'</div>
</body>
</html>';
}

/* 使用者刪除 */
/* ユーザー削除 */
function usrdel(){
	global $PIO, $FileIO, $PMS, $language;
	// $pwd: 使用者輸入值, $pwdc: Cookie記錄密碼
	$pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
	$pwdc = isset($_COOKIE['pwdc']) ? $_COOKIE['pwdc'] : '';
	$onlyimgdel = isset($_POST['onlyimgdel']) ? $_POST['onlyimgdel'] : '';
	$delno = array();
	reset($_POST);
	while($item = each($_POST)){ if($item[1]=='delete' && $item[0] != 'func') array_push($delno, $item[0]); }
	$haveperm = ($pwd==ADMIN_PASS) || adminAuthenticate('check');
	if($haveperm && isset($_POST['func'])){ // 前端管理功能
		$message = '';
		$PMS->useModuleMethods('AdminFunction', array('run', $delno, $_POST['func'], &$message)); // "AdminFunction" Hook Point
		if($_POST['func'] != 'delete'){
			if(isset($_SERVER['HTTP_REFERER'])){
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: '.$_SERVER['HTTP_REFERER']);
			}
			exit(); // 僅執行AdminFunction，終止刪除動作
		}
	}
	$PMS->useModuleMethods('Authenticate', array($pwd,'userdel',&$haveperm));

	if($pwd=='' && $pwdc!='') $pwd = $pwdc;
	$pwd_md5 = substr(md5($pwd),2,8);
	$host = gethostbyaddr(getREMOTE_ADDR());
	$search_flag = $delflag = false;

	if(!count($delno)) error(_T('del_notchecked'));

	$delposts = array(); // 真正符合刪除條件文章
	$posts = $PIO->fetchPosts($delno);
	foreach($posts as $post){
		if($pwd_md5==$post['pwd'] || $host==$post['host'] || $haveperm){
			$search_flag = true; // 有搜尋到
			array_push($delposts, $post['no']);
		}
	}
	if($search_flag){
		if(!$onlyimgdel) $PMS->useModuleMethods('PostOnDeletion', array($delposts, 'frontend')); // "PostOnDeletion" Hook Point
		$files = $onlyimgdel ? $PIO->removeAttachments($delposts) : $PIO->removePosts($delposts);
		$FileIO->deleteImage($files);
		deleteCache($delposts);
		total_size(true); // 刪除容量快取
		$PIO->dbCommit();
	}else error(_T('del_wrongpwornotfound'));
	if(isset($_POST['func']) && $_POST['func'] == 'delete'){ // 前端管理刪除文章返回管理頁面
		if(isset($_SERVER['HTTP_REFERER'])){
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.$_SERVER['HTTP_REFERER']);
		}
		exit();
	}
}

/* 管理員密碼認證 */
/* パス認証 */
function valid(){
	global $PMS, $language;
	$pass = isset($_POST['pass']) ? $_POST['pass'] : ''; // 管理者密碼
	$haveperm = false;
	$isCheck = adminAuthenticate('check'); // 登入是否正確
	if(!$isCheck && $pass){
		$haveperm = ($pass == ADMIN_PASS);
		$PMS->useModuleMethods('Authenticate', array($pass,'admin',&$haveperm));
		if($haveperm){ adminAuthenticate('login'); $isCheck = true; }
		else error(_T('admin_wrongpassword'));
	}
	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>] [<a href="'.PHP_SELF.'?mode=remake">'._T('admin_remake').'</a>] [<a href="'.PHP_SELF.'?page_num=0">'._T('admin_frontendmanage').'</a>]';
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'admin',$isCheck)); // LinksAboveBar hook point
	$dat .= '<div id="bannerlink">'.$links.'</div><div id="banner"><div class="passvalid">'._T('admin_top').'</div>
</div>
<form action="'.PHP_SELF.'" method="post" id="adminform">
<div id="admin-check" style="text-align: center;">
';//----class="bar_admin"
	echo $dat;
	if(!$isCheck){
		echo '<br />
<input type="radio" name="admin" value="del" checked="checked" />'._T('admin_manageposts').'
<input type="radio" name="admin" value="optimize" />'._T('admin_optimize').'
<input type="radio" name="admin" value="check" />'._T('admin_check').'
<input type="radio" name="admin" value="repair" />'._T('admin_repair').'
<input type="radio" name="admin" value="export" />'._T('admin_export').'
<input type="radio" name="admin" value="stylesheets" />'._T('admin_stylesheets').'<p />
<input type="hidden" name="mode" value="admin" />
<input class="inputtext" type="password" name="pass" size="8" />
<input type="submit" value="'._T('admin_verify_btn').'" />
</div>
</form>';
		die("\n</body>\n</html>");
	}elseif(!isset($_REQUEST['admin'])){
		echo '<br />
<input type="radio" name="admin" value="del" checked="checked" />'._T('admin_manageposts').'
<input type="radio" name="admin" value="optimize" />'._T('admin_optimize').'
<input type="radio" name="admin" value="check" />'._T('admin_check').'
<input type="radio" name="admin" value="repair" />'._T('admin_repair').'
<input type="radio" name="admin" value="export" />'._T('admin_export').'
<input type="radio" name="admin" value="stylesheets" />'._T('admin_stylesheets').'
<input type="radio" name="admin" value="logout" />'._T('admin_logout').'<p />
<input type="hidden" name="mode" value="admin" />
<input type="submit" value="'._T('admin_submit_btn').'" />
</div>
</form>';
		die("\n</body>\n</html>");
	}
}

/* 管理文章模式 */
/* 管理者削除 */
function admindel(){
	global $PIO, $FileIO, $PMS, $language;

	$pass = isset($_POST['pass']) ? $_POST['pass'] : ''; // 管理者密碼
	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0; // 切換頁數
	$onlyimgdel = isset($_POST['onlyimgdel']) ? $_POST['onlyimgdel'] : ''; // 只刪圖
	$modFunc = '';
	$delno = $thsno = array();
	$delflag = isset($_POST['func']) && ($_POST['func'] == 'delete') && isset($_POST['clist']); // 是否有「刪除」勾選
	$thsflag = isset($_POST['stop']); // 是否有「停止」勾選
	$is_modified = false; // 是否改寫檔案
	$message = ''; // 操作後顯示訊息

	if(isset($_POST['func']) && isset($_POST['clist']))
		$PMS->useModuleMethods('AdminFunction', array('run', $_POST['clist'], $_POST['func'], &$message)); // "AdminFunction" Hook Point

	// 刪除文章區塊
	if($delflag){
		//if(!adminAuthenticate('check')) error(_T('admin_wrongpassword'));

		$delno = array_merge($delno, $_POST['clist']);
		if($onlyimgdel != 'on') $PMS->useModuleMethods('PostOnDeletion', array($delno, 'backend')); // "PostOnDeletion" Hook Point
		$files = ($onlyimgdel != 'on') ? $PIO->removePosts($delno) : $PIO->removeAttachments($delno);
		$FileIO->deleteImage($files);
		deleteCache($delno);
		total_size(true); // 刪除容量快取
		$is_modified = true;
	}
	// 討論串停止區塊
	if($thsflag){
		//if(!adminAuthenticate('check')) error(_T('admin_wrongpassword'));

		$thsno = array_merge($thsno, $_POST['stop']);
		$threads = $PIO->fetchPosts($thsno); // 取得文章
		foreach($threads as $th){
			$flgh = $PIO->getPostStatus($th['status']);
			$flgh->toggle('TS');
			$PIO->setPostStatus($th['no'], $flgh->toString());
		}
		$is_modified = true;
	}
	if(($delflag || $thsflag) && $is_modified) $PIO->dbCommit(); // 無論如何都有檔案操作，回寫檔案

	$line = $PIO->fetchPostList(0, $page * ADMIN_PAGE_DEF, ADMIN_PAGE_DEF); // 分頁過的文章列表
	$posts_count = count($line); // 迴圈次數
	$posts = $PIO->fetchPosts($line); // 文章內容陣列

	echo '<input type="hidden" name="mode" value="admin" />
<input type="hidden" name="admin" value="del" />
<div style="text-align: left;">'._T('admin_notices').'</div>
<div>'.$message.'</div>
<table border="1" cellspacing="0" style="margin: 0px auto;">
<tr class="managehead">'._T('admin_list_header').'</tr>
';

	for($j = 0; $j < $posts_count; $j++){
		$bg = ($j % 2) ? 'row1' : 'row2'; // 背景顏色
		extract($posts[$j]);

		if($no == 0) break; // Do not display post 0

		// 修改欄位樣式
		$now = preg_replace('/.{2}\/(.{5})\(.+?\)(.{5}).*/', '$1 $2', $now);
		$name = htmlspecialchars(str_cut(html_entity_decode(strip_tags($name)), 8));
		$sub = htmlspecialchars(str_cut(html_entity_decode($sub), 8));
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		$com = str_replace('<br />',' ',$com);
		$com = htmlspecialchars(str_cut(html_entity_decode($com), 20));

		// 討論串首篇停止勾選框 及 模組功能
		$modFunc = $THstop = ' ';
		$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$j], $resto)); // "AdminList" Hook Point
		if($resto==0){ // $resto = 0 (即討論串首篇)
			$flgh = $PIO->getPostStatus($status);
			$THstop = '<input type="checkbox" name="stop[]" value="'.$no.'" />'.($flgh->exists('TS') ? _T('admin_stop_btn') : '');
		}

		// 從記錄抽出附加圖檔使用量並生成連結
		if($ext && $FileIO->imageExists($tim.$ext)){
			$clip = '<a href="'.$FileIO->getImageURL($tim.$ext).'" rel="_blank">'.$tim.$ext.'</a>';
			$size = $FileIO->getImageFilesize($tim.$ext);
			if($FileIO->imageExists($tim.'s.jpg')) $size += $FileIO->getImageFilesize($tim.'s.jpg');
		}else{
			$clip = $md5chksum = '--';
			$size = 0;
		}

		// 印出介面
		echo <<< _ADMINEOF_
<tr class="$bg" align="left">
<th align="center">$modFunc</th><th align="center">$THstop</th><th><input type="checkbox" name="clist[]" value="$no" />$no</th><td><small>$now</small></td><td>$sub</td><td><b>$name</b></td><td><small>$com</small></td><td>$host</td><td align="center">$clip ($size)<br />$md5chksum</td>
</tr>

_ADMINEOF_;
	}
	echo '</table>
<p>
<select name="func"><option value="delete">'._T('admin_delete').'</option>';
	$funclist = array();
	$PMS->useModuleMethods('AdminFunction', array('add', &$funclist, null, null)); // "AdminFunction" Hook Point
	foreach($funclist as $f) echo '<option value="'.$f[0].'">'.$f[1].'</option>';
	echo '</select>
<input type="submit" value="'._T('admin_submit_btn').'" /> <input type="reset" value="'._T('admin_reset_btn').'" /> [<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" /><label for="onlyimgdel">'._T('del_img_only').'</label>]</p>
<p>'._T('admin_totalsize',total_size()).'</p>
</div>
</form>
<hr />
';

	$countline = $PIO->postCount(); // 總文章數
	$page_max = ceil($countline / ADMIN_PAGE_DEF) - 1; // 總頁數
	echo '<table class="pages" border="1" style="float: left;"><tr>';
	if($page) echo '<td><a href="'.PHP_SELF.'?mode=admin&amp;admin=del&amp;page='.($page - 1).'">'._T('prev_page').'</a></td>';
	else echo '<td style="white-space: nowrap;">'._T('first_page').'</td>';
	echo '<td>';
	for($i = 0; $i <= $page_max; $i++){
		if($i==$page) echo '[<b>'.$i.'</b>] ';
		else echo '[<a href="'.PHP_SELF.'?mode=admin&amp;admin=del&amp;page='.$i.'">'.$i.'</a>] ';
	}
	echo '</td>';
	if($page < $page_max) echo '<td><a href="'.PHP_SELF.'?mode=admin&amp;admin=del&amp;page='.($page + 1).'">'._T('next_page').'</a></td>';
	else echo '<td style="white-space: nowrap;">'._T('last_page').'</td>';
	die('</tr></table><br/><br/>
</body>
</html>');
}

/* manage_css */
// http://www.magmagateau.com/fuukaba/ is the source of the code below
function manage_css(){
	global $PMS, $language;
	$order = array(); $line = '';

	// Handle submission
	if(isset($_POST['order']) || isset($_POST['new'])){
		$default = $_POST['default'];

		if(isset($_POST['order'])){
			$dupid = false;
			$order = $_POST['order'];
			$ids = array();
			$id = 0;
			$name = '';
			if(count($order) > 1){
				foreach($order as $name => $id) $ids[$id]++;
				foreach($ids as $idu => $count){
					if($idu < 1 || $count == 1) continue;
					foreach($order as $name => $id) if($id == $idu) $dupid = true;
				}
				if($dupid){
					ksort($order); // Yes, we're going to wimp out and reset it alphabetically.
					$go = 0;
					foreach($order as $name => $id) $go++;
					$order[$name] = $go;
				}
			}
			$order = array_flip($order);
		}

		// Find out if a new stylesheet entry has been added
		if(is_array($_POST['new'])){
			$new_path = $_POST['new']['path'];
			$new_name = $_POST['new']['name'];
			$new_order = $_POST['new']['order'];
			if($new_path != '' && $new_name != '') $order[$new_order] = $new_name;
			if($default == 'new') $default = $new_name;
		}

		ksort($order);

		// Get existing stylesheet entries
		foreach($order as $id => $name){
			if(!$id) continue;
			if(!$_POST['path'][$name]) continue;
			$temp['path'][$name] = $_POST['path'][$name];
			$temp['names'][$name] = $name;
			$temp['short'][$name] = $_POST['short'][$name];
			$temp['order'][$name] = $id;
		}

		// Get newly added stylesheet entries
		if(is_array($_POST['new'])){
			$temp['path'][$new_name] = $_POST['new']['path'];
			$temp['names'][$new_name] = $new_name;
			$temp['short'][$new_name] = $_POST['new']['short'];
			$temp['order'][$new_name] = $new_order;
		}

		// Build CSV lines
		foreach($order as $id => $name){
			if(!$id) continue; // Ignore Display order 0
			$line	.= $name.','.$temp['short'][$name].','.$temp['path'][$name].',';
			$line	.= ($name == $default) ? 'default,' : ',';
			$line	.= "\n";
		}

		$style_dat = DATA_DIR.CSV_SS;

		// Success messages
		$action = (file_exists($style_dat)) ? _t('admin_updated') : _t('admin_created');
		//---- $delete = (@unlink(CSS_DIR.CSVSS)) ? ' - <span class="warning">'.str_replace('[file]', CSS_DIR.CSVSS, _t('admin_deleted')).'</span>' : false;
		$fp = fopen($style_dat, 'w+');
		set_file_buffer($fp, 0);
		rewind($fp);
		fputs($fp, $line);
		fclose($fp);
		@chmod($style_dat, 0666);
		echo '<div class="bar_managecss">'.str_replace('[file]', $style_dat, _t('admin_updated'))./*----$delete.*/'</div>';
	}

	// Editing messages
	if(file_exists(DATA_DIR.CSV_SS)){
		$style_data = DATA_DIR.CSV_SS;
		$style_mode = _t('admin_editing');
//----	}else{
//----		$style_data = CSS_DIR.CSVSS;
//----		$style_mode = _t('admin_imported');
	}
	echo '<div class="banner"><table width="100%"><tr><th class="bar_managecss">
'.str_replace('[file]', $style_data, $style_mode).'
</th></tr></table></div>';

	echo '<div class="postlists">
<input type="hidden" name="mode" value="admin" />
<input type="hidden" name="admin" value="stylesheets" />
<table width="100%" border="1" cellspacing="0">';

	// Read data from R4+(CSV)
	if(file_exists(DATA_DIR.CSV_SS)){
		$style_data = DATA_DIR.CSV_SS;
		$lines = explode("\n", file_get_contents($style_data));
		$j = 0;
		$lines = array_diff($lines, array(''));
		if(count($lines)){
			echo '<tr class="managehead"><th colspan="2">'._t('admin_is_default').'</th><th>'._t('admin_fullname').'</th><th>'._t('admin_shortname').'</th><th>'._t('admin_dispid').'</th></tr>';
		}
		foreach($lines as $null => $line){
			if($line != ''){ // Line has data
				list($style_name, $style_short, $style_file, $style_rel) = explode(',', $line);
				$j++; // For alternating <tr> BG colour

				$style_path = CSS_DIR.$style_file;

				if(file_exists($style_path)){
					if($style_rel) $style_rel = ' checked="checked"';
					else $style_rel = false;
					$class = ($j % 2) ? 'row1' : 'row2'; // BG colour
					echo '<tr class="'.$class.'"><td align="left" colspan="2"><label><input name="default" type="radio"'.$style_rel.' value="'.$style_name.'" /> '.$style_path.'</label><input type="hidden" name="path['.$style_name.']" value="'.$style_file.'" /></td><td align="left">'.$style_name.'</td><td align="left"><input name="short['.$style_name.']" value="'.$style_short.'" size="5" /></td><td align="left"><label><input name="order['.$style_name.']" size="2" value="'.$j.'" /></label></td></tr>';
				}
			}
		}
	}

	// Read data from R3 DAT
//----	else if(file_exists(CSS_DIR.CSVSS)){
//----		$style_data = CSS_DIR.CSVSS;
//----		$lines = explode("\n", file_get_contents($style_data));
//----		$j = 0;
//----		foreach($lines as $null => $line){ // Line has data, or line is not commented out
//----			if(substr($line, 0, 2) != '//' && $line != ''){
//----				$bits = explode('[*]', $line);
//----				$j++;
//----				$style_name = $bits[0];
//----				$style_path = CSS_DIR.$bits[2];
//----				if(file_exists($style_path)){
//----					$style_short = $bits[1];
//----					if($bits[3]) $style_rel = ' checked="checked"';
//----					else $style_rel = false;
//----					$class = ($j % 2) ? 'row1' : 'row2'; // BG colour
//----					echo '<tr class="'.$class.'"><td align="left" colspan="2"><label><input name="default" type="radio"'.$style_rel.' value="'.$style_name.'" /> '.$style_path.'</label><input type="hidden" name="path['.$style_name.']" value="'.$bits[2].'" /></td><td align="left"><input name="names['.$style_name.']" value="'.$style_name.'" size="25" /></td><td align="left"><input name="short['.$style_name.']" value="'.$style_short.'" size="5" /></td><td align="left"><input name="order['.$style_name.']" size="2" value="'.$j.'" /></td></tr>';
//----				}
//----			}
//----		}
//----	}

	$j++; $class = ($j % 2) ? 'row1' : 'row2';

	// Insert string for when there are stylesheet entries to modify
	$action = ($j > 1) ? _t('admin_modstyle') : false;
	// Check default radio button for new row when there are NO stylesheet entries
	$style_rel = (!$action) ? ' checked="checked"' : false;

	echo '<tr class="managehead"><th>'.str_replace('[or modify]', $action, _t('admin_addstyle')).'</th><th>'._t('admin_new_file').'</th><th>'._t('admin_fullname').'</th><th>'._t('admin_shortname').'</th><th>'._t('admin_new_id').'</th></tr>';

	echo '<tr class="'.$class.'"><td align="left"><label><input name="default" type="radio"'.$style_rel.' value="new" /> '._t('admin_style_default').'</label></td><td align="left"><nobr>'.CSS_DIR.'<input name="new[path]" /></nobr></td><td align="left"><input name="new[name]" size="25" /></td><td align="left"><input name="new[short]" size="5" /></td><td align="left"><input name="new[order]" size="2" value="'.$j.'" /></td></tr></table></div>
<center><p><div class="passvalid"><input type="submit" value="'._T('admin_submit_btn').'" /> <input type="reset" value="'._T('admin_reset_btn').'" /></div></p>';
	die('</center></form></body></html>');
}

/* 計算目前附加圖檔使用容量 (單位：KB) */
function total_size($isupdate=false){
	global $config, $PIO, $FileIO;

	$size = 0; $all = 0;
	$cache_file = "./".SIZE_CACHE; // 附加圖檔使用容量值快取檔案

	if($isupdate){ // 刪除舊快取
		if(is_file($cache_file)) unlink($cache_file);
		return;
	}
	if(!is_file($cache_file)){ // 無快取，新增
		$line = $PIO->fetchPostList(); // 取出所有文章編號
		$posts = $PIO->fetchPosts($line);
		$linecount = count($posts);
		for($i = 0; $i < $linecount; $i++){
			extract($posts[$i]);
			// 從記錄檔抽出計算附加圖檔使用量
			if($ext && $FileIO->imageExists($tim.$ext)) $all += $FileIO->getImageFilesize($tim.$ext); // 附加圖檔合計計算
			if($FileIO->imageExists($tim.'s.jpg')) $all += $FileIO->getImageFilesize($tim.'s.jpg'); // 預覽圖合計計算
		}
		$sp = fopen($cache_file, 'w');
		stream_set_write_buffer($sp, 0);
		fwrite($sp, $all); // 寫入目前使用容量值
		fclose($sp);
		@chmod($cache_file, 0666);
	}else{ // 使用快取
		$sp = file($cache_file);
		$all = $sp[0];
		unset($sp);
	}
//----        return (int)($all / 1024);
	if(!$config['KB']) return (int)($all);
	else return (int)($all / 1024);
}

/* 搜尋(全文檢索)功能 */
function search(){
	global $PTE, $PIO, $FileIO, $PMS, $language;

	if(!USE_SEARCH) error(_T('search_disabled'));
	$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // 欲搜尋的文字
	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'search'));
	$dat .= '<div id="bannerlink">'.$links.'</div><div id="banner"><div class="bar_search">'._T('search_top').'</div>
</div>
';
	echo $dat;
	if($searchKeyword==''){
		echo '<form action="'.PHP_SELF.'" method="post">
<div id="search">
<input type="hidden" name="mode" value="search" />
';
		echo '<ul>'._T('search_notice').'<input class="inputtext" type="text" name="keyword" size="30" />
'._T('search_target').'<select name="field"><option value="com" selected="selected">'._T('search_target_comment').'</option><option value="name">'._T('search_target_name').'</option><option value="sub">'._T('search_target_topic').'</option><option value="no">'._T('search_target_number').'</option></select>
'._T('search_method').'<select name="method"><option value="AND" selected="selected">'._T('search_method_and').'</option><option value="OR">'._T('search_method_or').'</option></select>
<input type="submit" value="'._T('search_submit_btn').'" />
</li>
</ul>
</div>
</form>';
	}else{
		$searchField = $_POST['field']; // 搜尋目標 (no:編號, name:名稱, sub:標題, com:內文)
		$searchMethod = $_POST['method']; // 搜尋方法
		$searchKeyword = preg_split('/(　| )+/', trim($searchKeyword)); // 搜尋文字用空格切割
		$hitPosts = $PIO->searchPost($searchKeyword, $searchField, $searchMethod); // 直接傳回符合的文章內容陣列

		echo '<div id="search_result">
';
		$resultlist = '';
		foreach($hitPosts as $post){
			extract($post);
			if(USE_CATEGORY){
				$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
				$ary_category_count = count($ary_category);
				$ary_category2 = array();
				for($p = 0; $p < $ary_category_count; $p++){
					if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&amp;c='.urlencode($c).'">'.$c.'</a>';
				}
				$category = implode(', ', $ary_category2);
			}else $category = '';
			$arrLabels = array('{$NO}'=>'<a href="'.PHP_SELF.'?res='.($resto?$resto.'#r'.$no:$no).'">'.$no.'</a>', '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'));
			$resultlist .= $PTE->ParseBlock('SEARCHRESULT',$arrLabels);
		}
		echo $resultlist ? $resultlist : '<div style="text-align: center">'._T('search_notfound').'<br/><a href="?mode=search">'._T('search_back').'</a></div>';
		echo "</div>";
	}
	echo "</body>\n</html>";
}

/* 利用類別標籤搜尋符合的文章 */
function searchCategory(){
	global $PTE, $PIO, $PMS, $FileIO, $language;
	$category = isset($_GET['c']) ? strtolower(strip_tags(trim($_GET['c']))) : ''; // 搜尋之類別標籤
	if(!$category) error(_T('category_nokeyword'));
	$category_enc = urlencode($category); $category_md5 = md5($category);
	$page = isset($_GET['p']) ? @intval($_GET['p']) : 1; if($page < 1) $page = 1; // 目前瀏覽頁數
	$isrecache = isset($_GET['recache']); // 是否強制重新生成快取

	// 利用Session快取類別標籤出現篇別以減少負擔
	session_start(); // 啟動Session
	if(!isset($_SESSION['loglist_'.$category_md5]) || $isrecache){
		$loglist = $PIO->searchCategory($category);
		$_SESSION['loglist_'.$category_md5] = serialize($loglist);
	}else $loglist = unserialize($_SESSION['loglist_'.$category_md5]);

	$loglist_count = count($loglist);
	if(!$loglist_count) error(_T('category_notfound'));
	$page_max = ceil($loglist_count / PAGE_DEF); if($page > $page_max) $page = $page_max; // 總頁數

	// 分割陣列取出適當範圍作分頁之用
	$loglist_cut = array_slice($loglist, PAGE_DEF * ($page - 1), PAGE_DEF); // 取出特定範圍文章
	$loglist_cut_count = count($loglist_cut);

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>][<a href="'.PHP_SELF.'?mode=category&amp;c='.$category_enc.'&amp;recache=1">'._T('category_recache').'</a>]';
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'category'));
	$dat .= "<div>$links</div>\n";
	for($i = 0; $i < $loglist_cut_count; $i++){
		$posts = $PIO->fetchPosts($loglist_cut[$i]); // 取得文章內容
		$dat .= arrangeThread($PTE, ($posts[0]['resto'] ? $posts[0]['resto'] : $posts[0]['no']), null, $posts, 0, $loglist_cut[$i], array(), array(), false, false, false, 0, 0, 0, 0, 0); // 逐個輸出 (引用連結不顯示)
	}

	$dat .= '<table border="1"><tr>';
	if($page > 1) $dat .= '<td><form action="'.PHP_SELF.'?mode=category&amp;c='.$category_enc.'&amp;p='.($page - 1).'" method="post"><div><input type="submit" value="'._T('prev_page').'" /></div></form></td>';
	else $dat .= '<td style="white-space: nowrap;">'._T('first_page').'</td>';
	$dat .= '<td>';
	for($i = 1; $i <= $page_max ; $i++){
		if($i==$page) $dat .= "[<b>".$i."</b>] ";
		else $dat .= '[<a href="'.PHP_SELF.'?mode=category&amp;c='.$category_enc.'&amp;p='.$i.'">'.$i.'</a>] ';
	}
	$dat .= '</td>';
	if($page < $page_max) $dat .= '<td><form action="'.PHP_SELF.'?mode=category&amp;c='.$category_enc.'&amp;p='.($page + 1).'" method="post"><div><input type="submit" value="'._T('next_page').'" /></div></form></td>';
	else $dat .= '<td style="white-space: nowrap;">'._T('last_page').'</td>';
	$dat .= '</tr></table>'."\n";

	foot($dat);
	echo $dat;
}

/* 顯示已載入模組資訊 */
function listModules(){
	global $PMS, $language;
	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'modules'));
	$dat .= '<div id="bannerlink">'.$links.'</div><div id="banner"><div class="bar_info_mod">'._T('module_info_top').'</div>
</div>

<div id="modules">
';
	/* Module Loaded */
	$dat .= _T('module_loaded').'<ul>'."\n";
	foreach($PMS->getLoadedModules() as $m) $dat .= '<li>'.$m."</li>\n";
	$dat .= "</ul><hr />\n";

	/* Module Infomation */
	$dat .= _T('module_info').'<ul>'."\n";
	foreach($PMS->moduleInstance as $m) $dat .= '<li>'.$m->getModuleName().'<div style="padding-left:2em;">'.$m->getModuleVersionInfo()."</div></li>\n";
	$dat .= '</ul><hr />
</div>

';
	foot($dat);
	echo $dat;
}

/* 刪除舊頁面快取檔 */
function deleteCache($no){
	foreach($no as $n){
		if($oldCaches = glob('./cache/'.$n.'-*')){
			foreach($oldCaches as $o) @unlink($o);
		}
	}
}

/* 顯示系統各項資訊 */
function showstatus(){
	global $config, $PTE, $PIO, $FileIO, $PMS, $language, $LIMIT_SENSOR;
	$countline = $PIO->postCount(); // 計算投稿文字記錄檔目前資料筆數
	$counttree = $PIO->threadCount(); // 計算樹狀結構記錄檔目前資料筆數
	$tmp_total_size = total_size(); // 附加圖檔使用量總大小
	$tmp_ts_ratio = STORAGE_MAX > 0 ? $tmp_total_size / STORAGE_MAX : 0; // 附加圖檔使用量
	//++++
	$tome = time()+0*60*60;
	$youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
	$yd = $youbi[date('w', $tome)];
	$nowt = date($config['DATE_FORMAT'], $tome).'('.(string)$yd.')'.date($config['TIME_FORMAT'], $tome);
	if(!$config['KB']) $kbb = ' B';
	else $kbb = ' KB';

	// 決定「附加圖檔使用量」提示文字顏色
  	if($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
	elseif($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
	elseif($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
	elseif($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
	else $clrflag_sl = 'F2004A';

	// 生成預覽圖物件資訊及功能是否正常
	$func_thumbWork = '<span style="color: red;">'._T('info_nonfunctional').'</span>';
	$func_thumbInfo = '(No thumbnail)';
	if(USE_THUMB !== 0){
		$thumbType = USE_THUMB; if(USE_THUMB==1){ $thumbType = 'gd'; }
		require(PHP_DIRECTORY.'lib/thumb/thumb.'.$thumbType.'.php');
		$thObj = new ThumbWrapper();
		if($thObj->isWorking()) $func_thumbWork = '<span style="color: blue;">'._T('info_functional').'</span>';
		$func_thumbInfo = $thObj->getClass();
		unset($thObj);
	}

	// PIOSensor
	if(count($LIMIT_SENSOR))
		$piosensorInfo=nl2br(PIOSensor::info($LIMIT_SENSOR));

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>] [<a href="'.PHP_SELF.'?mode=moduleloaded">'._T('module_info_top').'</a>]';
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'status'));
	$dat .= '<div id="bannerlink">'.$links.'</div><div id="banner"><div class="bar_info">'._T('info_top').'</div>
</div>
';

	$dat .= '
<div id="status-table" style="text-align: center;">
<table border="1" style="margin: 0px auto; text-align: left;">
<tr><td align="center" colspan="4">'._T('info_basic').'</td></tr>
<tr><td style="width: 240px;">'._T('info_basic_ver').'</td><td colspan="3"> '.PIXMICAT_VER.' </td></tr>
<tr><td>'._T('info_basic_pio').'</td><td colspan="3"> '.PIXMICAT_BACKEND.' : '.$PIO->pioVersion().'</td></tr>
<tr><td>'._T('info_basic_threadsperpage').'</td><td colspan="3"> '.PAGE_DEF.' '._T('info_basic_threads').'</td></tr>
<tr><td>'._T('info_basic_postsperpage').'</td><td colspan="3"> '.RE_DEF.' '._T('info_basic_posts').'</td></tr>
<tr><td>'._T('info_basic_postsinthread').'</td><td colspan="3"> '.RE_PAGE_DEF.' '._T('info_basic_posts').' '._T('info_basic_posts_showall').'</td></tr>
<tr><td>'._T('info_basic_bumpposts').'</td><td colspan="3"> '.MAX_RES.' '._T('info_basic_posts').' '._T('info_basic_0disable').'</td></tr>
<tr><td>'._T('info_basic_bumphours').'</td><td colspan="3"> '.MAX_AGE_TIME.' '._T('info_basic_hours').' '._T('info_basic_0disable').'</td></tr>
<tr><td>'._T('info_basic_urllinking').'</td><td colspan="3"> '.AUTO_LINK.' '._T('info_0no1yes').'</td></tr>
<tr><td>'._T('info_basic_com_limit').'</td><td colspan="3"> '.COMM_MAX._T('info_basic_com_after').'</td></tr>
<tr><td>'._T('info_basic_anonpost').'</td><td colspan="3"> '.$config['ALLOW_NONAME'].' '._T('info_basic_anonpost_opt').'</td></tr>
<tr><td>'._T('info_basic_del_incomplete').'</td><td colspan="3"> '.KILL_INCOMPLETE_UPLOAD.' '._T('info_0no1yes').'</td></tr>
<tr><td>'._T('info_basic_use_sample',THUMB_Q).'</td><td colspan="3"> '.USE_THUMB.' '._T('info_0notuse1use').'</td></tr>
<tr><td>'._T('info_basic_useblock').'</td><td colspan="3"> '.BAN_CHECK.' '._T('info_0disable1enable').'</td></tr>
<tr><td>'._T('info_basic_showid').'</td><td colspan="3"> '.DISP_ID.' '._T('info_basic_showid_after').'</td></tr>
<tr><td>'._T('info_basic_cr_limit').'</td><td colspan="3"> '.BR_CHECK._T('info_basic_cr_after').'</td></tr>
<!--<tr><td>'._T('info_basic_timezone').'</td><td colspan="3"> GMT </td></tr>-->
<tr><td>サーバーのローカル時間</td><td colspan="3"> '.$nowt.' </td></tr>
<tr><td>'._T('info_basic_line_number_limit').'</td><td colspan="3"> '.$config['LINE_NUMBER']._T('info_basic_line_number_after').'</td></tr>
<tr><td>'._T('info_basic_theme').'</td><td colspan="3"> '.$PTE->BlockValue('THEMENAME').' '.$PTE->BlockValue('THEMEVER').'<br/>by '.$PTE->BlockValue('THEMEAUTHOR').'</td></tr>
<tr><td align="center" colspan="4">'._T('info_dsusage_top').'</td></tr>
<tr align="center"><td>'._T('info_basic_threadcount').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'"> '.$counttree.' '._T('info_basic_threads').'</td>'.(isset($piosensorInfo)?'<td rowspan="2">'.$piosensorInfo.'</td>':'').'</tr>
<tr align="center"><td>'._T('info_dsusage_count').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'">'.$countline.'</td></tr>
<tr><td align="center" colspan="4">'._T('info_fileusage_top').STORAGE_LIMIT.' '._T('info_0disable1enable').'</td></tr>';

	if(STORAGE_LIMIT){
		$dat .= '
<tr align="center"><td>'._T('info_fileusage_limit').'</td><td colspan="2">'.STORAGE_MAX.$kbb.'</td><td rowspan="2">'._T('info_dsusage_usage').'<br /><span style="color: #'.$clrflag_sl.'">'.substr(($tmp_ts_ratio * 100), 0, 6).'</span> %</td></tr>
<tr align="center"><td>'._T('info_fileusage_count').'</td><td colspan="2"><span style="color: #'.$clrflag_sl.'">'.$tmp_total_size.$kbb.'</span></td></tr>';
	}else{
		$dat .= '
<tr align="center"><td>'._T('info_fileusage_count').'</td><td>'.$tmp_total_size.$kbb.'</td><td colspan="2">'._T('info_dsusage_usage').'<br /><span style="color: green;">'._T('info_fileusage_unlimited').'</span></td></tr>';
	}

	$dat .= '
<tr><td align="center" colspan="4">'._T('info_server_top').'</td></tr>
<tr align="center"><td colspan="3">PHP version</td><td>'.phpversion().'</td></tr>
<tr align="center"><td colspan="3">'.$func_thumbInfo.'</td><td>'.$func_thumbWork.'</td></tr>
</table>
<hr />
</div>'."\n";

	foot($dat);
	echo $dat;
}

/* 程式首次執行之初始化 */
/* 初期設定 */
function init(){
	global $PIO, $FileIO, $language;
	if(!is_writable(realpath('./'))) error(_T('init_permerror'));

	$chkfolder = array(IMG_DIR, THUMB_DIR, 'cache/', TEMP_DIR);
	// 逐一自動建置資料夾
	foreach($chkfolder as $value) if(!is_dir($value)){ mkdir($value); @chmod($value, 0777); }  // 沒有就建立

	$PIO->dbInit(); // PIO Init
	$FileIO->init(); // FileIO Init

	error(_T('init_inited'));
}

/*-----------程式各項功能主要判斷-------------*/
/*-----------Main-------------*/
if(GZIP_COMPRESS_LEVEL && ($Encoding = CheckSupportGZip())){ ob_start(); ob_implicit_flush(0); } // 支援且開啟Gzip壓縮就設緩衝區
$mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_POST['mode']) ? $_POST['mode'] : ''); // 目前執行模式 (GET, POST)

//init(); // ←■■！程式環境初始化，跑過一次後請刪除此行！■■
switch($mode){
	case 'regist':
		regist();
		break;
	case 'admin':
		$admin = isset($_REQUEST['admin']) ? $_REQUEST['admin'] : ''; // 管理者執行模式
		valid();
		switch($admin){
			case 'del': admindel(); break;
			case 'logout':
				adminAuthenticate('logout');
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: '.fullURL().PHP_SELF2.'?'.time());
				break;
			case 'optimize':
			case 'check':
			case 'repair':
			case 'export':
				if(!$PIO->dbMaintanence($admin)) echo _T('action_main_notsupport');
				else echo _T('action_main_'.$admin).(($mret = $PIO->dbMaintanence($admin,true))?_T('action_main_success'):_T('action_main_failed')).(is_bool($mret)?'':'<br/>'.$mret);
				die("</div></form></body>\n</html>");
				break;
			case 'stylesheets': manage_css(); break;
			default:
		}
		break;
	case 'search':
		search();
		break;
	case 'status':
		showstatus();
		break;
	case 'category':
		searchCategory();
		break;
	case 'module':
		$loadModule = isset($_GET['load']) ? $_GET['load'] : '';
		if($PMS->onlyLoad($loadModule)) $PMS->moduleInstance[$loadModule]->ModulePage();
		else echo '404 Not Found';
		break;
	case 'moduleloaded':
		listModules();
		break;
	case 'init':
		init(); // ←■■！程式環境初始化，跑過一次後請刪除此行！■■
		break;
	case 'usrdel':
		usrdel();
	case 'remake':
		updatelog();
		header('HTTP/1.1 302 Moved Temporarily');
		header('Location: '.fullURL().PHP_SELF2.'?'.time());
		break;
	default:
		// 如果瀏覽器支援XHTML標準MIME就輸出
		header('Content-Type: '.((USE_XHTML && strpos($_SERVER['HTTP_ACCEPT'],'application/xhtml+xml')!==FALSE) ? 'application/xhtml+xml' : 'text/html').'; charset=utf-8');
		$res = isset($_GET['res']) ? $_GET['res'] : 0; // 欲回應編號
		if($res){ // 回應模式輸出
			$page = isset($_GET['page_num']) ? $_GET['page_num'] : 'RE_PAGE_MAX';
			if(!($page=='all' || $page=='RE_PAGE_MAX')) $page = intval($_GET['page_num']);
			updatelog($res, $page); // 實行分頁
		}elseif(isset($_GET['page_num']) && intval($_GET['page_num']) > -1){ // PHP動態輸出一頁
			updatelog(0, intval($_GET['page_num']));
		}else{ // 導至靜態庫存頁
			if(!is_file(PHP_SELF2)) updatelog();
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.fullURL().PHP_SELF2.'?'.time());
		}
}
if(GZIP_COMPRESS_LEVEL && $Encoding){ // 有啟動Gzip
	if(!ob_get_length()) exit; // 沒內容不必壓縮
	header('Content-Encoding: '.$Encoding);
	header('X-Content-Encoding-Level: '.GZIP_COMPRESS_LEVEL);
	header('Vary: Accept-Encoding');
	print gzencode(ob_get_clean(), GZIP_COMPRESS_LEVEL); // 壓縮內容
}





function thumb($path,$tim,$ext){
  if(!function_exists("ImageCreate") ||
     !function_exists("ImageCreateFromJPEG")){
    return;
  }

  $fname=$path.$tim.$ext;
  $thumb_dir = THUMB_DIR;
  $width     = MAX_W;
  $height    = MAX_H;
  $size = GetImageSize($fname);
  switch ($size[2]) {
    case 1 :
      if(function_exists("ImageCreateFromGIF")){
        $im_in = @ImageCreateFromGIF($fname);
        if($im_in){break;}
      }
      if(!is_executable(realpath("./gif2png")) || 
         !function_exists("ImageCreateFromPNG")){
        return;
      }

      @exec(realpath("./gif2png")." $fname",$a);

      if(!file_exists($path.$tim.'.png')){
        return;
      }
      $im_in = @ImageCreateFromPNG($path.$tim.'.png');
      unlink($path.$tim.'.png');
      if(!$im_in){
        return;
      }
      break;

    case 2 : 
      $im_in = @ImageCreateFromJPEG($fname);
      if(!$im_in){
        return;
      }
      break;
    case 3 :
      if(!function_exists("ImageCreateFromPNG")){
        return;
      }
      $im_in = @ImageCreateFromPNG($fname);
      if(!$im_in){
        return;
      }
      break;
    default : 
      return;
  }

  if ($size[0] > $width || $size[1] >$height) {
    $key_w = $width / $size[0];
    $key_h = $height / $size[1];
    ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
    $out_w = ceil($size[0] * $keys) +1;
    $out_h = ceil($size[1] * $keys) +1;
  } else {
    $out_w = $size[0];
    $out_h = $size[1];
  }

  if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
    $im_out = ImageCreateTrueColor($out_w, $out_h);
  }
  else{
    $im_out = ImageCreate($out_w, $out_h);
  }

  ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);

  ImageJPEG($im_out, $thumb_dir.$tim.'s.jpg',60);
  chmod($thumb_dir.$tim.'s.jpg',0666);

  ImageDestroy($im_in);
  ImageDestroy($im_out);
}




?>
