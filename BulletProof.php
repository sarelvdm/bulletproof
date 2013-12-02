<?php
/**
 * A small, secure & fast file uploader class, primarily meant for
 * image upload, such as jpg, gif, png and jpeg files.
 * @author     Simon _eQ <https://github.com/simon-eQ>
 * @license    Public domain. Do anything you want with it.
 */


class BulletProof
{
    /**
     * Give user an option to specify all options only once like a
     * a global setting, if not, they can use specify everything each time they call the upload method.
     * EXCEPT for the MIMEtype, which should be declared through the constructor.
     * @param array $allowedMimeTypes
     * @param array $allowedImageDimensions
     * @param array $allowedFileSize
     * @param null $newDirectoryToUpload
     */
    public function __construct(array $allowedMimeTypes,
                                array $allowedImageDimensions = null,
                                array $allowedFileSize = null,
                                      $newDirectoryToUpload = null)
    {
        $this->allowedMimeTypes         = $allowedMimeTypes;
        $this->allowedImageDimensions   = $allowedImageDimensions;
        $this->allowedFileSize          = $allowedFileSize;
        $this->newDirectoryToUpload     = $newDirectoryToUpload;
    }



    /**
     * Assign text for each of the possible errors that may be thrown by that $_FILES array.
     * @return array
     */
    public function commonFileUploadErrors()
    {
        /**
         * We can use the key identifier from $_FILES['error'] to match these arrays' keys and
         * output the corresponding errors. Damn I'm good! :D
         */
        return array(
            UPLOAD_ERR_OK	        => "...",
            UPLOAD_ERR_INI_SIZE 	=> "File is larger than the specified amount set by the server",
            UPLOAD_ERR_FORM_SIZE	=> "Files is larger than the specified amount specified by browser",
            UPLOAD_ERR_PARTIAL 		=> "File could not be fully uploaded. Please try again later",
            UPLOAD_ERR_NO_FILE		=> "File is not found",
            UPLOAD_ERR_NO_TMP_DIR	=> "Can't write to disk, as per server configuration",
            UPLOAD_ERR_EXTENSION	=> "A PHP extension has halted this file upload process"
        );
    }



    /**
     * If user wants to specify different file-size, upload dir, image-dimension in different
     * pages, then enable method-chaining to override the global settings passed
     * through the constructor (if they are passed, if not then this still will work).
     */

    /**
     * Pass image dimensions as associative arrays
     * @param array $setImageDimension
     * @return $this
     */
    public function setImageDimension(array $setImageDimension)
    {
        $this->allowedImageDimensions = $setImageDimension;
        return $this;
    }

    /**
     * Pass Max & Min, file size as associative arrays
     * @param array $setFileSize
     * @return $this
     */
    public function setFileSize(array $setFileSize)
    {
        $this->allowedFileSize = $setFileSize;
        return $this;
    }

    /**
     * Specify the new directory to upload your files,
     * if not specified, php will upload it to default tmp directory
     * @param $setUploadDir
     * @return $this
     */
    public function setUploadDir($setUploadDir)
    {
        $this->newDirectoryToUpload =$setUploadDir;
        return $this;
    }




    /**
     * There are many reasons for a file upload not work, other than from the information
     * obtained by the $_FILES[]['error'] array.
     * So, this function tends to debug server environment for a possible cause of an error,
     * if an error indeed happend.
     * @param null $newDirectory optional directory, if not specified this class will use tmp_name
     * @return string
     */
    public function debugEnviroment($newDirectory = null)
    {
        /**
         * If user has specified upload dir, check and debug it otherwise,
         * check the default dir given by PHP
         */
        $uploadFileTo = $newDirectory ? $newDirectory : init_get("file_uploads");

        /**
         * if the directory (if) specified by user is indeed dir or not
         */
        if(!is_dir($uploadFileTo))
        {
            return "Please make sure this is a valid directory, or php 'file_uploads' is turned on";
        }

        /**
         * Check if given directory has write permissions
         */
        if(!substr(sprintf('%o', fileperms($uploadFileTo)), -4) != 0777)
        {
            return "Sorry, you don't have her majesty's permission to upload files on this server";
        }

    }


    /**
     * Upload given files, after a series of validations
     * @param $fileToUpload
     * @param $newFileName
     * @return bool|string
     */
    public function upload($fileToUpload, $newFileName = null)
    {

        /**
         * First let's start with the easiest method, by checking if
         * $_FILES['name']['error'] is set. (means there is an error)
         */
        if($fileToUpload['error'])
        {
            $errors = $this->commonFileUploadErrors();
            return $errors[$fileToUpload['error']];
        }


        /**
         * Since the file type provided by $_FILES is unreliable due to
         * system/browser variations, we will double check the real type/extension
         * with SplFileInfo::getExtension
         */

        $splFileInfo       = new SplFileInfo($fileToUpload['name']);
        $splFileExtension  = $splFileInfo->getExtension();

        /**
         * get rid of the 'image/' part from ex: 'image/gif'
         */
        $fileTypeExtension = substr($fileToUpload['type'], 6);


        /**
         * Since getExtension and FILES[]['type'] given often different
         * names, ex: 'jpeg vs jpg' '.doc vs application/msword'
         * We can't really check if both are same, so the best bet is to check
         * if they are both inside the $allowedMimeTypes set by the user
         */
        if(!in_array($fileTypeExtension, $this->allowedMimeTypes) ||
           !in_array($splFileExtension, $this->allowedMimeTypes))
        {
            return "This is not allowed File type. Please only upload ("
                . implode(' ,', $this->allowedMimeTypes) .") file types";
        }

        /**
         * Once file is validated, retain the real extension for a later use
         */
        $this->fileExtension = ".".$splFileExtension;


        /**
         * Check if file size is within the scope of what the user has defined.
         */
        if($fileToUpload['size'] > $this->allowedFileSize['max-size'] ||
            $fileToUpload['size'] < $this->allowedFileSize['min-size'])
        {
            return "File size must be less than between
                    ".(implode(",", $this->allowedFileSize))." kilobytes";
        }


        /**
         * IMPORTANT:
         * If users has already set a value for $allowedFileDimensions, it means user is trying to
         * upload an image, that means we can validate the image size, if however $allowedFileDimensions is not
         * set, then we'll assume user want to upload other files, so image size is irrelevant here.
         */
        if($this->allowedFileDimensions)
        {
            list($width, $height, $type, $attr) = getimagesize($fileToUpload['tmp_name']);

            if($width > $this->allowedImageDimensions['max-width'] ||
               $height > $this->allowedImageDimensions['min-width'])
            {
                return "Image must be less than "
                        .$this->allowedImageDimensions['max-width']."pixels wide and"
                        .$this->allowedImageDimensions['max-height']."pixels in height";
            }


            if($height <= 1 || $width <=1)
            {
                return "This file is either too small or corrupted to be an image file";
            }

        }

        /**
         * Check weather user wants to give a file name to each uploaded files.
         */
        if($newFileName)
        {
            /**
             * If given a file name, then assign it and append the new extension obtained
             * from the SplFileInfo::getExtension();
             */
            $this->newFileName = $newFileName.$this->fileExtension;
        }else{

            /**
             * Hehehe, create a 74 digit length id for the file. not sure if this is bad implementation
             * If user has not provided any name, just generate a unique id
             */
            $uniqid = uniqid(str_shuffle(implode(range(1, 30))), true);
            $this->newFileName = $uniqid.$this->fileExtension;
        }


        /**
         * According the the PHP manual, is_uploaded_file is mandatory to check
         * as an additional security check.
         */
        $checkSafeUpload = is_uploaded_file($fileToUpload['tmp_name']);

        /**
         * Move the file to a new user-specified destination
         */
        $moveUploadFile = move_uploaded_file($fileToUpload['tmp_name'],
                                            $this->directoryToUpload.'/'.
                                            $this->newFileName);


        /**
         * Check if every validation has gone as expected.
         * If true, return the new file name with the extension as a positive response.
         */
        if($checkSafeUpload && $moveUploadFile)
        {
            return $this->newFileName;
        }
        else
        {
            /**
             * If file upload has not worked for any reason, the debug the server environment and it's
             * permission, settings etc.. for possible errors.
             */
            $checkServerForErrors = $this->debugEnviroment($this->diretoryToUpload);

            /**
             * If error is found from the debugEnviroment() return the error, otherwise show an error
             */
            return $checkServerForErrors ? $checkServerForErrors : "Unknown error occured, please try later";
        }


    }

}
