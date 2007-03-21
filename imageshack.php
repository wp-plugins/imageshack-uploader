<?php
/*
 Info for WordPress:
 ==============================================================================
 Plugin Name: ImageShack Uploader 
 Plugin URI: http://www.arnebrachhold.de/projects/wordpress-plugins/imageshack-uploader
 Description: Allows you to upload images to ImageShack.com directly from your posting screen.
 Version: 1.0b
 Author: Arne Brachhold
 Author URI: http://www.arnebrachhold.de/


License and Copyright:
 ==============================================================================
 Copyright 2007  ARNE BRACHHOLD  (email : himself [a|t] arnebrachhold [dot] de)

 THIS SOFTWARE IS NOT GPL! It is copyright and all rights reserved. I (Arne Brachhold)
 grant you the the following rights:
 
 - You may FREELY distribute this software in an UNMODIFIED state.
 - You may NOT CHARGE for the software or any distribution costs, however, 
   you may charge for technical support for the software, including but not 
   limited to, installation, customisation, and upgrading.
 - I allow derivatives but any major additions and changes must be provided 
   to me so that I can make those changes freely available to the community.
 - Anything else is subject to prior written permission by Arne Brachhold. 
   If you contact me, there is a good chance we will say yes to any reasonable request.
   
 What this mean in practice: This plugin is "free software", in that it is absolutely 
 free to download, free to use and even free to tinker with (although I typically would 
 require any modifications made to it to be clearly indicated to potential users and 
 supplied to me). What I don't want to see, though, is people grabbing a version of 
 WordPress and this plugin, packaging them together and selling them (as they could do, 
 with GPL software). Bottom line is that I am not making money with this, and I don’t 
 see why somebody else should be able to without me having a say first.

 Once again, this type of licensing doesn’t make any difference for 99% of users 
 (it’s free for whatever you need it to do), and shouldn’t stand in the way of the 
 remaining 1% with more specific needs. If you have doubt or questions, contact me.
 I'm very open to any discussion or criticism regarding this format of licensing.  

 This software is provided "as is", without any guarantee of warranty of any kind, 
 nor could I ever be held liable for any damages it could do to your system.

*/


class ImageShackFile {

	var $imageLink = '';
	var $thumbLink = '';
	var $adLink = '';
	
	var $thumbExists = false;
	var $width = 0;
	var $height = 0;
	var $fileSize = 0;
	
	
	public function FromXML($xml) {
		//PHP + XML sucks cause you never know what's installed...
		preg_match_all("/(<([\w]+)[^>]*>)(.*)(<\/\\2>)/", $xml, $matches, PREG_SET_ORDER);
		
		$tags = array();
		
		foreach ($matches as $val) {
			$tagName = $val[2];
			$value = $val[3];	
			
			$tags[$tagName] = $value;
		}
		
		if(!empty($tags["image_link"])) {
		
			$img = new ImageShackFile();
			$img->imageLink = (string) $tags["image_link"];
			
			if(!empty($tags["thumb_link"])) {
				$img->thumbLink = (string)  $tags["thumb_link"];	
			}
			
			if(!empty($tags["ad_link"])) {
				$img->adLink = (string) $tags["ad_link"];	
			}
			
			if(!empty($tags["thumb_exists"])) {
				$img->thumbExists = ($tags["thumb_exists"]=="yes"?true:false);	
			}
			
			if(!empty($tags["resolution"])) {
				$wh = explode("x",$tags["resolution"]);
				if(count($wh)==2) {
					$img->width = intval($wh[0]);
					$img->height = intval($wh[0]);
				}
			}
			
			if(!empty($tags["filesize"])) {
				$img->fileSize = intval($tags["filesize"]);
			}
			return $img;			
		}
		return null;
	}
}


class ImageShack {
	
	function Enable() {
		if(!isset($GLOBALS["is_instance"])) {			
			
			$GLOBALS["is_instance"]=new ImageShack();
			add_filter("wp_upload_tabs",array(&$GLOBALS["is_instance"],"HtmlAddTab"));	
		}
	}	
	
	function Upload($fileName) {	
		$result = null;

		$ch = curl_init("http://www.imageshack.us/index.php");

		$post['xml']='yes';
		$post['fileupload']='@' . $fileName;

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 240);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: '));

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}
	
	function HtmlAddTab($tabs) {
		// 0 => tab display name, 1 => required cap, 2 => function that produces tab content, 3 => total number objects OR array(total, objects per page), 4 => add_query_args		
		$tabs["imageshack"]=array(__("ImageShack","imageshack"),"edit_posts",array($this,"HtmlGetTab"),0);
		
		return $tabs;
	}
	
	function HtmlGetTab() {
		?>
		<script type="text/javascript">
			function imageshack_insert(img,link) {
				if(!img) return;
				h = '';
				if(link) {
					h+='<a href="' + link + '">';
				}
				h+='<img src="' + img + '" border="0" alt="ImageShack" />';
				if(link) {
					h+='</a>';
				}
				
				var win = window.opener ? window.opener : window.dialogArguments;
				if ( !win )
					win = top;
				tinyMCE = win.tinyMCE;
				if ( typeof tinyMCE != 'undefined' && tinyMCE.getInstanceById('content') ) {
					tinyMCE.selectedInstance.getWin().focus();
					tinyMCE.execCommand('mceInsertContent', false, h);
				} else
					win.edInsertContent(win.edCanvas, h);
			}
		</script>
		<div style="padding:10px;">
		<?php
		
		if(function_exists("curl_init")) {
		
			if(isset($_POST["action"]) && $_POST["action"]=="upload") {
				check_admin_referer( 'inlineuploading' );
				if(isset($_FILES["image"])) {
					$f = $_FILES["image"];
					if(!empty($f["error"])) {
						echo "<p>" . __("Sorry, there was an error while uploading the file from yout to this server. The error was: ","imageshack") . htmlspecialchars($f["error"]) . "</p>";
					} else {
						$result = $this->Upload($f["tmp_name"]);
						if($result && strpos($result,"<?xml ")!==false) {
							//#type $file ImageShackFile
							$file = ImageShackFile::FromXML($result);
							if($file!==null) {
								if($file->thumbExists) {
									echo '<img src="' . $file->thumbLink . '" alt="Uploaded Image (Thumb)" style="float:left; margin:10px;" />';	
								} else {
									echo '<img src="' . $file->imageLink . '" alt="Uploaded Image (No thumb available)" style="float:left; margin:10px; margin-right:20px; width:100px; height:100px;" />';	
									echo __("ImageShack didn't create a thumbnail for your images, most likely because it's too small.","imageshack");
								}	
								
								echo "<ul style=\"list-style-position: inside;\">";
								if($file->thumbExists) {
									echo '<li><a href="javascript:imageshack_insert(\'' . $file->thumbLink . '\',\'' . $file->imageLink . '\'); void(0);">'. __("Insert thumbnail with link to image","imageshack") . '</a></li>';
									echo '<li><a href="javascript:imageshack_insert(\'' . $file->thumbLink . '\',\'' . $file->adLink . '\'); void(0);">' . __("Insert thumbnail with link to image page","imageshack") . '</a></li>';
								}
								echo '<li><a href="javascript:imageshack_insert(\'' . $file->imageLink . '\'); void(0);">' . __("Insert image directly","imageshack"). '</a></li>';
								echo "</ul>";
							} else {
								echo "<p>" . __("Sorry, ImageShack returned something strange.","imageshack") . "</p>";	
							}	
						} else {
							echo "<p>" . __("Sorry, upload to ImageShack failed.","imageshack") . "</p>";	
						}	
					}			
				} else {
					echo "<p>" . __("Hey, you need to choose a file to upload ;)","imageshack") . "</p>";		
				}
				echo '<p><a href="' .  get_option('siteurl') . "/wp-admin/upload.php?style=inline&amp;tab=imageshack" . '">' . __("Upload another file","imageshack") . '</a>';
			} else {
			?>
			
				<img src="<?php echo $_SERVER["PHP_SELF"]; ?>?res={9TV0BAD8C-77FA-4842-956E-CKLF7635F2C7}" alt="ImageShack Logo" style="width:75px; height:65px; float:left;  margin:10px;"  />
				<form enctype="multipart/form-data" xid="upload-file" method="post" action="<?php echo get_option('siteurl') . "/wp-admin/upload.php?style=inline&amp;tab=imageshack"; ?>">
					<p><?php echo __("Upload your image to ImageShack here. After you've uploaded the file, you can insert it directly into your post. Depending on the image size it may need some time, so please press the upload button only once.","imageshack"); ?></p>
					<table><col /><col class="widefat" />
						<tr>
							<th scope="row"><label for="upload"><?php _e('File'); ?></label></th>
							<td><input type="file" id="upload" name="image" /></td>
						</tr>
						<tr id="buttons" class="submit">
							<td colspan='2'>
								<input type="hidden" name="from_tab" value="<?php echo $tab; ?>" />
								<input type="hidden" name="action" value="upload" />
								<?php wp_nonce_field( 'inlineuploading' ); ?>
								<div class="submit">
									<input type="submit" value="<?php _e('Upload'); ?> &raquo;" />
								</div>
							</td>
						</tr>
					</table>
				</form>
			<?php
			}
		} else {
			_e("Uuuhm, sorry. You need to install <a target=\"_blank\" href=\"http://www.google.com/search?q=curl\">curl</a> first or ask your admin to do that for you.","imageshack");	
		}
		?>
		</div>
		<?php	
	}
	
}

ImageShack::Enable();

#region Embedded resources
if(isset($_GET["res"]) && !empty($_GET["res"])) {
	$resources = array(
		"{9TV0BAD8C-77FA-4842-956E-CKLF7635F2C7}"
		=>"iVBORw0KGgoAAAANSUhEUgAAAEsAAABBCAMAAACuC6m+AAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ"
		. "2VSZWFkeXHJZTwAAAMAUExURf7CEf/LEv/lNf3HKP/kFao3EXAuFMSKENm3LvjVFP3ZFbyiEP29EYZ0Da+mLvO0ElVJCZmDDmdYCu"
		. "OjEXlpC/O7Eu+9J9V7EEQ6B8ZqELh2ENiWENeID+uzEv7HEeaYEPuzEeeLEfTRFLSbD7qKFOTEE/KtEuylEOvKE9Z1D8OUD//8Reu"
		. "sEbtlEOelELpCEKGfM8i/ROSrEvHNE//aM8qSEP7UHjErB6qDEzsxBykiBsRZELmTFv25EVRQJduREJMrEv7KHYokFqyUDuKSEP7V"
		. "LP/ZHCMWBst3EOOeD/KiErVaEM1yEP7EGd2LEKKMD//cFYJqC9G1Ed6mEOy7EsyFEP/yNndiFNOCEKtuEN2+Ev/SNMarEGZZGBEMC"
		. "dmBEP/wGK3Nw/XAEsuuEriCEdybEEtCCOi2Eat7D5R6GIc1FODBEv/kI6hoD5B8DIR1Fv/pIplREdyyEa5DEP7kK/bUFJS3qvmsEs"
		. "t8ENObEduhEKdcENSRD5lGEdypE3WMZ1dLE5tjDSgwKP/RJaOXG8OoEf/rLXRYEf/0J+qeEPTDGVEUDnVXCvGnEvW9G+mTEIxEFOX"
		. "PReN5EKp0D+OCEKdLEO3BE7pbEJc1EvSdEZVXEP/WF5eLMsuzEsFwEP/NMNGNEGkbEPy8HWdAGWloGOO6FNysHf/REf/dIcF6EF9R"
		. "Ce25GOKyHEU8EmBHCtOlEHNFGLdqEMCmEfvXFfHLKJJtDbZ8EOy0GVgrCP/PF92/Jf/VElc4FeWrHdW4EjwkCdCrE+SeGty9HG9iC"
		. "//eKtTHSkRZP6BWD+DLG09HE3VADINtEcuhG//eN8msHOWxENqzGuvCGT1PNkUkFLdNEF1cHO/REtNmD/OwFiooFf/ZEsNhEP/gFR"
		. "wjJHwiFT44DMPl1+rIMXpzLOrcL8uhJJlvKu+VEOW4HNazF/q3FDZHSqAvEf+nEUE8IlYhEVE+Ct+eGHx0HtCnG72nJcapF8qdEGt"
		. "fKvDiOB0eIP3XPffIGP/PEIJPEIxgC/LQFAAAAFOenpEAAAEAdFJOU///////////////////////////////////////////////"
		. "/////////////////////////////////////////////////////////////////////////////////////////////////////"
		. "/////////////////////////////////////////////////////////////////////////////////////////////////////"
		. "///////////////////////////////////////////////////////////////////////////////////////////wBT9wclAAA"
		. "L/ElEQVR42pyYe1QTdxbHJ2qIMyQkhETCK+AEQtAQuzwcZsQNCBiCkIw8REDB8ggCShFMAQGrIigqaH0jFVCrQihYHcXiA2xj1/qg"
		. "9IGtrZTso2vLssruWtYudNP9JQhIwW31/jHnJDn5nHvv797v796Bfh4zIzDzw/jzyxk0SjEaURQde74sy8zRwggMwwiCoEYUoV6OB"
		. "gGUFkGMbu3W3JXcbMulHA7ndgogvgQMAqj22ys33RTcfcXK6m2Mt/Wtt2JxlvZlAoVAgjRc68cfftSzCtilt9549Y9vZOAsUvvipw"
		. "BYEoHvwi5XNhNY8p//9vrrr76x5Z+YNcgdjL4YDrC03P/udnR0Zifz+f96uGVL4o/fJD5UazhcVpnEfArGF2DB3J8qbGROycIh4fc"
		. "PE4H9+M1XG7+weuXtZgOrDKbQ33wOIPeUfqBALq3aMTREi1/5zR+AbVlpc7p82fYnj982cFNQ+LeeA6gJOBI7ssDW+WLVjuQuv4db"
		. "Pvtsy03/6GShUDg8n8ziYTdfk/zGgzDVF8nryJt3XyVr2nFyMOPE51e2pXZFs4H1ubTNWPWR1U2BWkLCWu2v0kx1r5Vw8BJ6np3K5"
		. "p2ZH/tfuNBa6CUVO/GT+eyIGTmrtpdvvWmwjs+SIL9GM/eQ1puH3erv7w4v/nbmJ9dy071slFInJzaf3Vczg+zJyVll9ftmg4Dnhh"
		. "j/b6yQubW1iAi70h/Q3x1aeGrmn7wPeimByaTu+VDb5a8vt/WsWvbmh7+7i4tAqObWn5r3VCfQHjWxpz8ggL4k1OG7T735MrlcHhz"
		. "s7MKk0YZpwhpk+PKaZXOtcH2KmwRFntf70FPpQiW65swAYPTu+45SPl+ZlGTr6AxBTJoQYgoXmqBkzkf65uabnGy1JUxNFS00qoNU"
		. "O3blMGAtCuinLwh3cFge/qWtMhnqY0JstrjSBaIJaacvk5LcH4K4OsGmoyZt+mUVQ2OiSqmJdwFs0aKAgP55CwqW2y2wlfGTxZXiC"
		. "mUFH6LRIEgIgaKj0YTD3tkYS0vCKKWdAIPGFBqFs7F3AcpkwLX7dh7BUjFb7KxSyfhDQiEEWEwQcx9zoYsL84xAx+LejHWbABtnAT"
		. "0FsN5eM8vkWbGz1IkvTfPwqGDz+xYymQDHPA4BkrgyLF/Kem3rUT3HG52KNQIjTiwCtMMl9O7a+jSll1NXVLGH3MbdvbqyKgICvON"
		. "9+fnse/fuiav/fnnVqhkGS2pKlglGqbETvb0B64qW1NZHpUenp3ulFSxXKt1B1kBL9fUxmfkuffekYnHlg9lhIIdc9XNYJhgZhJ3I"
		. "LLlxtpVRmHvwYJPXhvpwxzSlzL2Szb4nrqyqygfMSndgFcEV1dUuLdnIc1gmmJZzZc+ekiJ7RuHG9IMXvZpmxTjK5UpZBegpsUxaA"
		. "ZhSd3eZu7Oz7fvTZ9/AI2HjeKVNYJkqQ0ScyMwsWdLIKPRKb6qaP21WwXK5Uuksk1VU2ATv311RUeHsrKwIVtnujpiFc4B6oDAFjx"
		. "TaRJYJFoRdscg8cNY+Ji19aKimofOqrSo4LThYtX+2Ctj+/apg8EG1PGnvg+OnNnHcYColKEVrhk1ioaQlgB0+4Gnn6DQ01FazuPN"
		. "9W0e5ysNRpZLLASzJMclk4R5Ju8P6Sg2iL7iGu4ZYs2e/YJkucZB/16KAvEPhadHJ0HBNQ4g83NbDY7ltkqNjki3AgCf4bOuxf3Z1"
		. "oM6ac/fJ3L9oblNTsMwpM5wqPgCa3FbGdhGerllzPvS+XUFoaEFoeHi4bTigApSdR5Iq7HggNzF27txp608S7fCULJLX0hVjd2BRf"
		. "3eSLGyh8PTi8llLPGtra+2A1YaHFwAsgMqlx+sS6lw5T5aVd04X6vTgQCeztFm438YYu/t5vYvoSbOrmcNtDeWn4lrtz3p61trbNz"
		. "Y2FhQUxBQEV02vS0ioqwpZVj7N5zhzTrMRNU7OF5I90LQhpvbQe0RHSfeXe/NBlA3ldzbHrW5ttU9NZbgyGDG+DvJ7gBSSUFdXXl4"
		. "+rdQlf3UzOgULNWoynHwbPfMUmD437taB2UAFa9asT928b/Xq1lTGnDm+DN9Ch7C6hJCQkNLAhvI1630C81Xv8ajJMRoREf7OBkZt"
		. "N93VMnuAwLAjoO+Ga9Y8idu8b9++VkADfhUHH/cBFhLYlpOz5nppRNhmImty7kGI+m3RDrVL6BYrB/DsFZJsYq0LJGybn3Np3ZG1c"
		. "fvWxtm3goQ51yWYWBENgFUa4eK8iwMKbBILZN7fq/4QvZfIVruBa4dUYyoXppBWU/6txZ4jN9bdKFp71vNQWIJPp49PKQ2g5kP5D4"
		. "px0OOTWZQI35jW2N2vYPWY71ZUqznyoBJUBrnd9ZhFZuaeknUH6HmVdT7rO/8zvS2nYbHQpTp4NQF6fHJvk6wWqW/jvGOYt/mWBvr"
		. "I6gh/kL9QONyz/a87H1mYdPdYXnXdtPJpndMDGxbTXMTBDi06BDVO1hxKv01aX0tXZJMjOgIuO86tvbsrmbSaGeevfP75o0c7FYrM"
		. "/YHry5ct6wy53rZQ7O7oR5yjJvT2yFhOZmGusphaC4XpXJ56qsaXJ+1m02oWN/AUGIYLWPpHX0ZcB4PGep+ECHdZfUyLjnpWc0wcm"
		. "ELQFZxNTWn1jA4Wio6yEO62q/94XwruoLalvKz2LAkpIubdW/z15cun2yLESnntBcWKZ1hG00ThnaLO1uCxTdFRhb4C1miIRvgo8e"
		. "1FGxt3cZ8L87/cHjA7wRKNxWym6Y6jBcpUtXEdSynUOL4rwEi8tcag0/lF0/hRGwp9dbyxdCGc2FleUTZKZYUsbDMHARnVeosUR3b"
		. "L2Gzh6SEHz7MtAiCuz8xM8TycE5QbtWHHjugohzNnBgd4PWYWiDCIOHX1qldUGlCavbdM56UWEERH5gJbuY1TctfZon2EJWwcZ8Ei"
		. "/N8zc5e2rM49GeUgL/5+cG0LoabMixbshvk1gXEsrT78/rxeogwEQCje3bxvHb3brsAxzX5dHK5DRu9uyNw2hsf6xETewWuxA+/du"
		. "nOpBcMVAsTMIvUtgdHpUQ71BXbzjhHnwLKjJ/wGGXFr19L76d1L1v3UgcfD4yzglyVHkcj9wY+Dc7KtBZw7hChLbWked6ky7FRydF"
		. "dacYEnQB0lkTJc4H/Gl8GYc/Zwb+/hWx0KgRs1NlKY80VlYdbnNPhSkREh2w04Hg92DhNKixouRIC7G6C6+xPVJMoieLlRvr4bcxl"
		. "FhxcF7CFYQW7w+HQykvuj2DY8W0Ka1jOt2wo3khoZJEneAN/JK60+9H43PZFLoVxMdHLQj/Hd+Y2NRSUlN34yUOSzg87IvIoG6a3b"
		. "240kBS462MjiiMxukSJso1OUQ0zoobz+nToJ8P72hQGC8+n8g1GpcTeKWndpJk5z0Oi4atjUrGEFRRqRnjIsg7AG9QXHY35sG4cYu"
		. "0N59J2YG6V102BEouYSAs1xLWSsTh0cYCGTZzngAkff/oF/7KZmAc8yiDhnSUSSWq0mlr3BASi/CRVJmdKqx17zWTynhSBa/AfnXD"
		. "OoSeMUczSlxg0CzoV3mgaXajQKzY4MPJLkCZw2FD/1yiQEVLvOYHX9TAvBXXEOnPa1FHwFZZxqjgYpD1JrsBY/J8g76/vppRkYR1C"
		. "oBKglefRjChEIBqxNK988f4HQR1IUGUncjk8RxGunZBnB8k6SKSyBghUYMr8tsPTO7Y2yGHCAIO0KEThXsBpyZvoLcEtzuSDZ+Adb"
		. "DRMmzGdncspNxOJxBc2PO31C2oRDbRFSuV13Hr1/J15mLhGYO7CLYEkQc7nARxUZgl+kftQvFC3TN2+6be1/ifbJxxeZyclDyclOD"
		. "sArkKss0qzWcCRXZ0mO7FdAbXkCrtvEEEdZWm/8K6tL16yX7hrAcMOtmDQvPr8ryte+6JgiBRn5u1FLUbBxVNZQWKLVGqdkod5qgU"
		. "Gg0fFY6pSgbB3W0bEtIzWm8cYjRdBoIKZXKmNri+mCQp+/w2SlxHtrERKBKRJOCbIWEM0WHYRgxXhOJixAUy1r43qvHdvnnkq/DuN"
		. "aGl/kdczEe8j4VE1NPEQrgakXen0CPef7l3njBD33lxd/EfY/AQYAWOH6IyVS3QQAAAAASUVORK5CYII="
	);
	if(array_key_exists($_GET["res"],$resources)) {
		$key = $_GET["res"];
		$content = base64_decode($resources[$key]);
			
		$lastMod = filemtime(__FILE__);
		$client = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])?$_SERVER['HTTP_IF_MODIFIED_SINCE']:false);
		// Checking if the client is validating his cache and if it is current.
		if (isset($client) && (strtotime($client) == $lastMod)) {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
			exit;
		} else {
			// Image not cached or cache outdated, we respond '200 OK' and output the image.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
			header('Content-Length: '.strlen($content));
			header('Content-Type: image/png');
			echo $content;
			exit;
		}	
	}
}

