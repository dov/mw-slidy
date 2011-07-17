<?php
# Copyright (C) 2005 TooooOld <tianshuen@gmail.com>
# http://www.mediawiki.org/
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
# http://www.gnu.org/copyleft/gpl.html

/**
 * Extension to create slidy show
 *
 * @author TooooOld <tianshuen@gmail.com>
 * @package MediaWiki
 * @subpackage Extensions
 */

if( !defined( 'MEDIAWIKI' ) ) {
	die();
}

define( 'SLIDY_INC_MARK', 	'(step)' );
define( 'SLIDY_PAGE_BREAK', '\\\\\\\\' );
$ce_slidy_tpl_file 	= "./extensions/mw-slidy/slidy.htm";

$wgExtensionFunctions[] = 'setupSlidyShow';

function setupSlidyShow() {

	global $wgParser, $wgRequest;

    $wgParser->setHook( 'slidy', 'renderSlidy' );

	$ce_gen			= $wgRequest->getText('ce_gen', false);
	$ce_slidy_title   = $wgRequest->getText('title', false);

	$ce_slidy		= $wgRequest->getText('ce_slidy', false);
	$ce_slidy_style	= $wgRequest->getText('ce_style', false);

	if($ce_slidy){
		$ceTitle =& Title::newFromURL($ce_slidy_title);
		$slidyShow = new slidyShow($ceTitle, $ce_slidy_style);

		$slidyShow->genSlidyFile();
	}
}

function renderSlidy( $style = 'default',$args = null, $parser = null ) {

 	global $wgTitle, $wgScriptPath;

	if(is_object($wgTitle)){
        $slidyShow = new slidyShow($wgTitle, $style, $args);
		$url = $wgTitle->escapeLocalURL("ce_style=$style&ce_slidy=true");
		return '<div class="floatright"><span>
				<a href="'.$url.'" class="image" title="Slidy Show" target="_blank">
				<img src="'.$wgScriptPath.'/extensions/slidy/'.$slidyShow->style.'/'.$slidyShow->style.'.gif" alt="Slidy Show" width="240px" /><br />
				Slidy Show</a></span></div>';
	}else{
		return "this is a slidy show page.\n";
	}
}

class slidyShow
{
 	var $sTitle;
 	var $style;
 	var $mContent;
 	var $mSlidys;
 	var $ts;

	function slidyShow($slidyTitle, $style = 'default', $args = null){
		
		if(is_object($slidyTitle)){
		 	$this->sTitle = $slidyTitle->getFullText();
			$slidyArticle = new Article( $slidyTitle );
			$this->ts = $slidyArticle->getTimestamp();
			$this->mContent = $slidyArticle->getContent(0);
			$this->setStyle($style);
			$this->slidyParser();
            
            if (!isset( $args["author"] ) ) { $args["author"] = "Foo Bar"; }
            $this->author = $args["author"];

		}else{
			wfDebug("Slidy: Error! Pass a title object, NOT a title string!\n");
		}
	}
	
	function setStyle($style = 'default'){
		$this->style = $style;
	}

	function slidyParser(){
		$secs = preg_split(
			'/(^==[^=].*?==)(?!\S)/mi',
			$this->mContent, -1,
			PREG_SPLIT_DELIM_CAPTURE);

		$this->mSlidys = array();

		$secCount = count($secs);
		for($i=1; $i<$secCount; $i=$i+2)
		{
		 	$this->mSlidys[] = array('title' => str_replace('==', '', $secs[$i]),
                                     'content' => $secs[$i+1]);
		}
		$this->desc = $secs[0];
		return true;
	}

	function genSlidyFile(){
		global $ce_slidy_tpl_file, $ce_file_dir, $ce_slidy_tpl,
				$wgUser, $wgContLang, $wgOut;

	 	if(empty($this->mSlidys)){
	 	 	return false;
	 	}

		#get template
		$ce_slidy_tpl = @file_get_contents($ce_slidy_tpl_file);
		if( '' == $ce_slidy_tpl ){

			return false;
		}

		#generate content
		$fc = '';
		$s = "<div class=\"slide\"><h1>%s</h1>%s</div>\n";

		$options =& ParserOptions::newFromUser( $wgUser );
		$fileParser = new Parser;
		
		$fileParser->setHook( 'slidy','ceFakeSlidy' );
		$nt = & Title::newFromText( $this->sTitle );

		foreach( $this->mSlidys as $slidy ){
			
			$title = $slidy['title'];

//			if( ! preg_match( '/^'.SLIDY_PAGE_BREAK.'/mi', $slidy['content']) ){
			if( ! preg_match( '/'.SLIDY_PAGE_BREAK.'$/mi', $slidy['content']) ){
//			if( ! strpos( $slidy['content'], SLIDY_PAGE_BREAK ) ){
				$output =& $fileParser->parse($slidy['content']."\n__NOTOC__\n__NOEDITSECTION__", $nt, $options);
				$slidyContent = $output->getText();
				if(strpos($title, SLIDY_INC_MARK)){
					$slidyContent = str_replace('<ul>', '<ul class="incremental">', $slidyContent);
					$slidyContent = str_replace('<ol>', '<ol class="incremental">', $slidyContent);
					$title = str_replace(SLIDY_INC_MARK, '', $title);
				}
				$fc .= sprintf($s, $title, $slidyContent);
			} else {
//				$ms = explode( SLIDY_PAGE_BREAK, $slidy['content'] );
				$ms = preg_split( '/'.SLIDY_PAGE_BREAK.'$/mi', $slidy['content'] );
				$sc = count($ms);
				foreach( $ms as $i=>$ss ){
					$title = $slidy['title'] . " (".($i+1)."/$sc)";
					$output =& $fileParser->parse($ss."\n__NOTOC__\n__NOEDITSECTION__", $nt, $options);
					$slidyContent = $output->getText();
					if(strpos($title, SLIDY_INC_MARK)){
						$slidyContent = str_replace('<ul>', '<ul class="incremental">', $slidyContent);
						$slidyContent = str_replace('<ol>', '<ol class="incremental">', $slidyContent);
						$title = str_replace(SLIDY_INC_MARK, '', $title);
					}
					$fc .= sprintf($s, $title, $slidyContent);
				}
			} //<--} else {
		} //<--foreach( $this->mSlidys as $slidy ){
		
		$output =& $fileParser->parse($this->desc."\n__NOTOC__\n__NOEDITSECTION__", $nt, $options);
		$desc = $output->getText();

		#write to file
		$ce_page_search = array( '[desc]', '[slidyContent]', '[slidyTitle]', '[slidyStyle]','[slidyAuthor]' );
		$ce_page_replace= array( $desc, $fc, $this->sTitle, $this->style, $this->author );
		$fileContent = str_replace($ce_page_search, $ce_page_replace, $ce_slidy_tpl);
		$fileContent = $wgContLang->Convert($fileContent);

		$wgOut->disable();
		echo($fileContent);
		exit();
	}

}

function ceFakeSlidy(){}

?>
