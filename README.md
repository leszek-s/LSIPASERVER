# LSIPASERVER

Single file php script for uploading and downloading ipa files with over the air (OTA) installation

## Installation

To install just upload this single index.php file to your https server.

You also need to update SERVER_ADDRESS variable in the script with your server address.
Optionally you can also change default password for uploading ipa files in UPLOAD_KEY
variable, disable list of all ipa files in ALLOW_ALL_UPLOADS_LIST variable, and set
maximum ipa files size in MAX_IPA_SIZE.

## Usage

If you uploaded index.php to https://yourserver.com/ipaserver/ then:

open https://yourserver.com/ipaserver/ without params to see the list of all uploaded ipa files

open https://yourserver.com/ipaserver/?upload to see the form for uploading ipa file

open https://yourserver.com/ipaserver/?c=token to see the page for downloading/installing specific ipa file

## Command line ipa upload with curl

You can also upload ipa file from command line with curl:

curl -F "title=TestProject" -F "bundleId=com.test.testproject" -F "bundleVersion=1.0" -F "uploadKey=secretkey" -F "fileToUpload=@TestProject.ipa" https://yourserver.com/ipaserver/

## License

LSIPASERVER is available under the MIT license.
