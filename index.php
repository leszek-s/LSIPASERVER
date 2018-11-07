<?php

// LSIPASERVER
// 
// Copyright (c) 2018 Leszek S
// 
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
// 
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

// Installation:
// To install just upload this single index.php file to your https server and update
// $SERVER_ADDRESS below to your server address
// optionally change $UPLOAD_KEY, $ALLOW_ALL_UPLOADS_LIST, $MAX_IPA_SIZE below
//
// Usage:
// open index.php without params to see list of all uploaded ipa files
// open index.php?upload to see the form for uploading ipa
// open index.php?c=token to see page for downloading/installing specific uploaded ipa
//
// Command line ipa upload with curl:
// curl -F "title=TestProject" -F "bundleId=com.test.testproject" -F "bundleVersion=1.0" -F "uploadKey=secretkey" -F "fileToUpload=@TestProject.ipa" https://exampleserver.com/ipaserver/

error_reporting(0);

// password needed for uploading ipa files
$UPLOAD_KEY = "secretkey";
// if true page with all uploaded ipa list is available, otherwise only pages for valid specific ipa tokens are available
$ALLOW_ALL_UPLOADS_LIST = true;
// maximum allowed size of uploaded ipa file
$MAX_IPA_SIZE = 1024 * 1024 * 100;
// absolute server address to a directory with this index.php file, this is used for generating proper urls in manifests
$SERVER_ADDRESS = "https://exampleserver.com/ipaserver/";

prepareUploadsDir();

if (isset($_POST["bundleId"]) && isset($_POST["bundleVersion"]) && isset($_POST["title"]) && isset($_POST["uploadKey"]) && isset($_FILES["fileToUpload"]) && $_FILES["fileToUpload"]["size"] > 0) {
	handleUpload();
} else if (isset($_GET["upload"])) {
	showPageWithUploadForm();
}
else {
	showPageWithList($_GET["c"]);
}

function prepareUploadsDir() {
	if (!is_dir('uploads')) {
    	mkdir('uploads', 0777, true);
    	touch('uploads/index.html');
	}
}

// returns manifest.plist content
function generateManifest($ipaUrl, $bundleId, $bundleVersion, $title) {
	$manifest = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>items</key>
  <array>
    <dict>
      <key>assets</key>
      <array>
        <dict>
          <key>kind</key>
          <string>software-package</string>
          <key>url</key>
          <string>'.$ipaUrl.'</string>
        </dict>
      </array>
      <key>metadata</key>
      <dict>
        <key>bundle-identifier</key>
        <string>'.$bundleId.'</string>
        <key>bundle-version</key>
        <string>'.$bundleVersion.'</string>
        <key>kind</key>
        <string>software</string>
        <key>title</key>
        <string>'.$title.'</string>
      </dict>
    </dict>
  </array>
</dict>
</plist>';
	return $manifest;
}

// returns random 8 characters token not yet used in uploaded files names
function generateRandomToken() {
	$allFiles = scandir("uploads");
	$randomToken = substr(str_shuffle("0123456789abcdefghijklmnopqrstvwxyz"), 0, 8);
	$randomTokenIsOK = true;
	while (true) {
		foreach ($allFiles as $file) {
			if (strpos($file, $randomToken) !== false) {
			    $randomTokenIsOK = false;
			}
		}
		if ($randomTokenIsOK) {
			break;
		} else {
			$randomToken = substr(str_shuffle("0123456789abcdefghijklmnopqrstvwxyz"), 0, 8);
		}
	}
	return $randomToken;
}

// saves a file with given content
function saveFile($fileName, $content) {
	$myFile = fopen($fileName, "w");
	fwrite($myFile, $content);
	fclose($myFile);
}

// handles ipa upload, saves ipa and manifest
function handleUpload() {
	$bundleId = $_POST["bundleId"];
	$bundleId = preg_replace("/[^A-Za-z0-9-.]/", "", $bundleId);
	$bundleVersion = $_POST["bundleVersion"];
	$bundleVersion = preg_replace("/[^0-9.]/", "", $bundleVersion);
	$title = $_POST["title"];
	$title = preg_replace("/[^A-Za-z0-9-._ ]/", "", $title);
	$uploadKey = $_POST["uploadKey"];
	$targetDir = "uploads/ls_";
	$targetDate = date("YmdHis");
	$randomToken = generateRandomToken();
	$filePath = $targetDir.$bundleId."_".$bundleVersion."_".$targetDate."_".$randomToken;
	$ipaFilePath = $filePath.".ipa";
	$manifestFilePath = $filePath.".plist";
	$serverAbsolutePath = $GLOBALS["SERVER_ADDRESS"];
	$ipaAbsolutePath = $serverAbsolutePath.$ipaFilePath;
	$manifestContent = generateManifest($ipaAbsolutePath, $bundleId, $bundleVersion, $title);
	$uploadOk = true;
	$status = "";
	
	if ($uploadKey !== $GLOBALS["UPLOAD_KEY"]) {
		$status .= "Sorry, wrong upload key. ";
		$uploadOk = false;
	}
	if (file_exists($ipaFilePath)) {
		$status .= "Sorry, file already exists. ";
		$uploadOk = false;
	}
	if ($_FILES["fileToUpload"]["size"] > $GLOBALS["MAX_IPA_SIZE"]) {
		$status .= "Sorry, your file is too large. ";
		$uploadOk = false;
	}
	
	if ($uploadOk) {
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $ipaFilePath)) {
			saveFile($manifestFilePath, $manifestContent);
			$status .= "The file has been uploaded. <br />";
			$status .= '<a style="color: #A00; text-decoration: none" href="?c='.$randomToken.'">[ Download ]</a>';
		} else {
			$status .= "Sorry, there was an error uploading your file. ";
		}
	} else {
		$status .= "Your file was not uploaded. ";
	}
	
	showPageWithStatus($status);
}

// shows a page with given status string
function showPageWithStatus($status) {
	echo '<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; background-color: #F3F3F3">
<center>
<h1>Upload status</h1>
<h3 style="color: #A00">'.$status.'</h3>
<h3>Powered by LSIPASERVER</h3>
</center>
</body>
</html>';
}

// shows a page with upload form
function showPageWithUploadForm() {
	echo '<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; background-color: #F3F3F3">
<center>
<h1>Upload IPA</h1>
<form method="post" enctype="multipart/form-data">
<h3>
<input type="text" name="title" size="50" placeholder="Title"><br />
<input type="text" name="bundleId" size="50" placeholder="Bundle ID"><br />
<input type="text" name="bundleVersion" size="50" placeholder="Bundle Version"><br />
<input type="text" name="uploadKey" size="50" placeholder="Upload Key"><br />
<input type="file" name="fileToUpload" id="fileToUpload">
<input type="submit" value="Upload" name="submit">
</h3>
</form>
<h3>Powered by LSIPASERVER</h3>
</center>
</body>
</html>';
}

// returns directory content sorted by date
function sortedScanDir($dir) {
    $allFiles = scandir($dir);
    $files = array();    
    foreach ($allFiles as $file) {
        $files[$file] = filemtime($dir.'/'.$file);
    }
    arsort($files);
    $files = array_keys($files);
    return ($files) ? $files : false;
}

// shows a page with list of uploaded ipa files or with a single ipa if any token is passed in the parameter
function showPageWithList($tokenFilter) {
	echo '<!DOCTYPE html><html><body style="font-family: Arial, Helvetica, sans-serif; background-color: #F3F3F3"><center><h1>Download IPA</h1>';
	if ($GLOBALS["ALLOW_ALL_UPLOADS_LIST"] || isset($tokenFilter)) {
		$allFiles = sortedScanDir("uploads");
		$counter = 0;
		foreach ($allFiles as $file) {
			$matches = array();
			if (preg_match('/ls_([A-Za-z0-9-.]+)_([0-9.]+)_([0-9]+)_([a-z0-9]+)\.plist/', $file, $matches)) {
				$bundleId = $matches[1];
				$bundleVersion = $matches[2];
				$targetDate = $matches[3];
				$randomToken = $matches[4];
			
				if (isset($tokenFilter) && $tokenFilter != $randomToken) {
					continue;
				}
		
				$counter++;
				$uploadDate = date_create_from_format('YmdHis', $targetDate);
				$formattedDate = date_format($uploadDate, 'Y-m-d H:i:s');
				$manifestFilePath = "uploads/".$file;		
				$serverAbsolutePath = $GLOBALS["SERVER_ADDRESS"];
				$manifestAbsolutePath = $serverAbsolutePath.$manifestFilePath;
				$ipaFilePath = substr($manifestFilePath, 0, -5).'ipa';	
			
				$backgroundColor = $counter % 2 == 0 ? '#F0F0F0' : '#F6F6F6';
			
				echo '<div style="font-size: 1.4em; font-weight: bold; background-color: '.$backgroundColor.'; padding: 10px">Identifier: '.$bundleId.'<br />Version: '.$bundleVersion.'<br />Timestamp: '.$formattedDate.'<br />';
			
				if (isset($tokenFilter)) {
					echo '<a style="color: #A00; text-decoration: none" href="'.$ipaFilePath.'">[ Download ]</a> &nbsp; <a style="color: #A00; text-decoration: none" href="itms-services://?action=download-manifest&url='.$manifestAbsolutePath.'">[ Install ]</a>';
				} else {
					echo '<a style="color: #A00; text-decoration: none" href="?c='.$randomToken.'">[ Download ]</a>';
				}
			
				echo '</div>';
			}
		}
	}
	echo '<h3>Powered by LSIPASERVER</h3></center></body></html>';
}

?>