<?php
/**
 * PdfFactory.php
 *
 * Create pdf documents with TCPDF and FPDI.
 *
 * Usage: see readme.md
 *
 * @author Joe Blocher <yii@myticket.at>
 * @copyright 2014 myticket it-solutions gmbh
 * @license New BSD License
 * @category User Interface
 * @package joblo/pdffactory
 * @version 1.0.0
 */
class EPdfFactory extends CApplicationComponent
{
    /**
     * The path to the TCPDF library.
     *
     * Configure this attribute as alias with dot,
     * a relativ path from webroot or an absolute path with slash.
     *
     * @var string
     */
    public $tcpdfPath='ext.pdffactory.vendors.tcpdf';

    /**
     * The path to the FPDI library.
     * Configure this attribute as alias with dot,
     * a relativ path from webroot or an absolute path with slash.
     *
     * @var string
     */
    public $fpdiPath='ext.pdffactory.vendors.fpdi';

    /**
     * Configure the caching.
     * -1 = cache disabled
     * 0 = a generated pdf never expires
     * >0 expire in hours
     *
     * @var int
     */
    public $cacheHours=0; //-1 = no cache, 0=never expires, >0 expire hours

    /**
     * The directory path where the pdf template files for FPDI are located.
     * Attribute with Getter/Setter.
     *
     * Configure this attribute as alias with dot,
     * a relativ path from webroot or an absolute path with slash.
     *
     * Default is 'application.pdf.templates'
     *
     * @var string
     */
    protected $_templatesPath;

    /**
     * The directory path, where the pdf doc will be created/cached.
     * Attribute with Getter/Setter.
     *
     * Configure this attribute as alias with dot,
     * a relativ path from webroot or an absolute path with slash.
     *
     * Default is 'application.runtime.pdf'
     * @var string
     */
    protected $_pdfPath;

    /**
     * The default options for the constructor of the TCPDF/FPDI class.
     * @see vendors/tcpdf.php TCPDF::__construct for details
     *
     * Default value = array(
    'format'=>'A4',
    'orientation'=>'P', //=Portrait or 'L' = landscape
    'unit'=>'mm', //measure unit: mm, cm, inch, or point
    'unicode'=>true,
    'encoding'=>'UTF-8',
    'diskcache'=>false,
    'pdfa'=>false,
    )
     *
     * @var array()
     */
    protected $_tcpdfOptions;

    /**
     * The path to the pdfable extension as alias path.
     *
     * @var string
     */
    public $pdfableExt='ext.pdfable';

    /**
     * The options of the WkHtmlToPdf component.
     *
     * @var array
     */
    public $htmlToPdfOptions = array(
        //'bin'   => 'D:\wkhtmltopdf\wkhtmltopdf.exe',  // path to executable (default)
        // 'dpi'   => 600,
    );

    /**
     * The page options of the WkHtmlToPdf component.
     * @var array
     */
    public $htmlToPdfPageOptions = array(
        // 'page-size'         => 'A5',
        // 'user-style-sheet'  => Yii::getPathOfAlias('webroot').'/css/pdf.css',
    );


    /**
     * Internal vars for memory caching
     */
    private $_tcpdf;
    private $_fpdi;
    private $_wkhtmltopdf;
    private $_pdfObject;
    private $_currentCacheFiles=array();

    /**
     * Set the tcp
     *
     * @param mixed $tcpOptions
     */
    public function setTcpdfOptions($tcpOptions)
    {
        $this->_tcpdfOptions = array_merge(array(
            'format'=>'A4',
            'orientation'=>'P',
            'unit'=>'mm',
            'unicode'=>true,
            'encoding'=>'UTF-8',
            'diskcache'=>false,
            'pdfa'=>false,
        ),$tcpOptions);
    }

    /**
     * Get the tcpfOptions
     *
     * @return mixed
     */
    public function getTcpdfOptions()
    {
        if(!isset($this->_tcpdfOptions))
            $this->setTcpdfOptions(array());

        return $this->_tcpdfOptions;
    }


    /**
     * Set the pdfPath
     *
     * @param string $pdfPath
     */
    public function setPdfPath($pdfPath)
    {
        $this->_pdfPath = $this->translatePath($pdfPath);
    }

    /**
     * Get the path where to create the pdf files on rendering/caching
     *
     * @return string
     */
    public function getPdfPath()
    {
        if(!isset($this->_pdfPath))
            $this->setPdfPath('application.runtime.pdf');

        if(!is_dir($this->_pdfPath))
            mkdir($this->_pdfPath,0777,true);

        return $this->_pdfPath;
    }

    /**
     * Set the template path
     *
     * @param string $templatesPath
     */
    public function setTemplatesPath($templatesPath)
    {
        $this->_templatesPath = $this->translatePath($templatesPath);
    }

    /**
     * Get the template path for pdf template files.
     *
     * @return string
     */
    public function getTemplatesPath()
    {
        if(!isset($this->_templatesPath))
            $this->setTemplatesPath('application.pdf.templates');

        return $this->_templatesPath;
    }

    /**
     * Get a FPDI instance.
     *
     * @param array $tcpdfOptions
     * @return null
     */
    public function getFPDI($tcpdfOptions = array())
    {
        if($this->_fpdi === null)
        {
            $this->createFPDI($tcpdfOptions);
        }

        $this->_pdfObject = $this->_fpdi;
        return $this->_fpdi;
    }

    /**
     * Get a TCPDF instance.
     *
     * @param array $tcpdfOptions
     * @return null
     */
    public function getTCPDF($tcpdfOptions = array())
    {
        if($this->_tcpdf === null)
        {
            $this->createTCPDF($tcpdfOptions);
        }

        $this->_pdfObject = $this->_tcpdf;
        return $this->_tcpdf;
    }

    /**
     * Set the current pdf object
     *
     * @param mixed $pdfObject
     */
    public function setPdfObject($pdfObject)
    {
        $this->_pdfObject = $pdfObject;
    }

    /**
     * Get a WkHtmlToPdf instance from the extension pdfable
     *
     * @return null
     */
    public function getWkHtmlToPdf()
    {
        if($this->_wkhtmltopdf === null)
        {
            $this->createWkHtmlToPdf();
        }

        $this->_pdfObject = $this->_wkhtmltopdf;
        return $this->_wkhtmltopdf;
    }

    /**
     * Output the pdf to the specified destination.
     * Checks cached file.
     *
     * @param $name
     * @param null $dest
     * @return string
     * @throws CHttpException
     */
    public function output($name, $dest=null)
    {
        $dest = $this->getValidatedDestination($dest);
        $cachedFile = $this->getCachedFile($name);
        if(!empty($cachedFile))
            return $this->outputPdfFile($cachedFile,$name,$dest);
        else
        {
            if(empty($this->_pdfObject))
                throw new CHttpException('No pdf instance created: Call one of the methods createTCPDF(),createFPDI() before output.');

            if(strpos($dest,'F')===0)
            {
                $name = $this->getPdfPath().DIRECTORY_SEPARATOR.$name;
                $this->_pdfObject->output($name,$dest);
                return is_file($name) ? $name : '';
            }
            else
                return $this->_pdfObject->output($name,$dest);
        }
    }


    /**
     * Include the FPDI and TCPDF lib
     *
     * @param bool $includeTCPDF include the fpdi2pcpdf_bridge?
     */
    public function includeFPDI()
    {
        $this->includeTCPDF();
        $libPath=$this->translatePath($this->fpdiPath);
        require_once($libPath . DIRECTORY_SEPARATOR . 'fpdi.php');
    }

    /**
     * Include the TCPDF lib only
     */
    public function includeTCPDF()
    {
        $libPath=$this->translatePath($this->tcpdfPath);
        require_once($libPath . DIRECTORY_SEPARATOR . 'tcpdf.php');
    }

    /**
     * Create a TCPDF pdf document
     *
     * @param string $class
     * @param array $params
     * @return mixed
     */
    public function createTCPDF($tcpdfOptions = array())
    {
        $this->includeTCPDF();
        $opt = $this->getTcpdfOptions();
        if(!empty($tcpdfOptions))
            $opt=array_merge($opt,$tcpdfOptions);
        $this->_tcpdf = new TCPDF($opt['orientation'],$opt['unit'],$opt['format'],$opt['unicode'],$opt['encoding'],$opt['diskcache'],$opt['pdfa']);
        $this->_pdfObject = $this->_tcpdf;
        return $this->_tcpdf;
    }

    /**
     * Create a TCPDF pdf document
     *
     * @param array $tcpdfOptions
     * @param bool $useTCPDFBridge
     * @return FPDI
     */
    public function createFPDI($tcpdfOptions = array())
    {
        $this->includeFPDI();
        $opt = $this->getTcpdfOptions();
        if(!empty($tcpdfOptions))
            $opt=array_merge($opt,$tcpdfOptions);
        $this->_fpdi = new FPDI($opt['orientation'],$opt['unit'],$opt['format'],$opt['unicode'],$opt['encoding'],$opt['diskcache'],$opt['pdfa']);
        $this->_pdfObject = $this->_fpdi;
        return $this->_fpdi;
    }

    /**
     * Create a custom pdf document inherited from TCPDF
     *
     * @param string $class
     * @param array $params
     * @return mixed
     */
    public function createCustomTCPDF($class,$tcpdfOptions = array())
    {
        $this->includeTCPDF();
        $opt = $this->getTcpdfOptions();
        if(!empty($tcpdfOptions))
            $opt=array_merge($opt,$tcpdfOptions);
        $this->_tcpdf = new $class($opt['orientation'], $opt['unit'],$opt['format'],$opt['unicode'],$opt['encoding'],$opt['diskcache'],$opt['pdfa']);
        $this->_pdfObject = $this->_tcpdf;
        return $this->_tcpdf;
    }


    /**
     * Create a custom pdf document inherited from FPDI
     * @param $class
     * @param array $tcpdfOptions
     * @param bool $useTCPDFBridge
     * @return mixed
     */
    public function createCustomFPDI($class,$tcpdfOptions = array())
    {
        $this->includeFPDI();
        $opt = $this->getTcpdfOptions();
        if(!empty($tcpdfOptions))
            $opt=array_merge($opt,$tcpdfOptions);
        $this->_fpdi = new $class($opt['orientation'], $opt['unit'],$opt['unit'],$opt['format'],$opt['encoding'],$opt['diskcache'],$opt['pdfa']);
        $this->_pdfObject = $this->_fpdi;
        return $this->_fpdi;
    }

    /**
     * Create a WkHtmlToPdf instance from the pdfable extension
     *
     * @param $htmlToPdfPageOptions
     * @param $htmlToPdfOptions
     * @return WkHtmlToPdf
     */
    public function createWkHtmlToPdf($htmlToPdfPageOptions=array(), $htmlToPdfOptions=array())
    {
        Yii::import($this->pdfableExt . '.*');
        $this->_wkhtmltopdf = new WkHtmlToPdf;

        $htmlToPdfOptions = array_merge($this->htmlToPdfOptions, $htmlToPdfOptions);
        $htmlToPdfPageOptions = array_merge($this->htmlToPdfPageOptions, $htmlToPdfPageOptions);

        $this->_wkhtmltopdf->setOptions($htmlToPdfOptions);
        $this->_wkhtmltopdf->setPageOptions($htmlToPdfPageOptions);

        $this->_pdfObject = $this->_wkhtmltopdf;
        return $this->_wkhtmltopdf;
    }


    /**
     * Attach the pdfable extension behavior to a controller
     *
     * @param $controller
     * @param $htmlToPdfPageOptions
     * @param $htmlToPdfOptions
     */
    public function attachPdfableBehavior($controller, $htmlToPdfPageOptions = array(), $htmlToPdfOptions = array())
    {
        if (empty($this->pdfableExt))
            throw new CException('Extension pdfable not configured');

        $htmlToPdfOptions = array_merge($this->htmlToPdfOptions, $htmlToPdfOptions);
        $htmlToPdfPageOptions = array_merge($this->htmlToPdfPageOptions, $htmlToPdfPageOptions);

        $options = array('class' => $this->pdfableExt.'.Pdfable',
            'pdfOptions' => array_merge($this->htmlToPdfOptions, $htmlToPdfOptions),
            'pdfPageOptions' => array_merge($this->htmlToPdfPageOptions, $htmlToPdfPageOptions)
        );

        $controller->attachBehavior('pdfable', $options);
    }


    /**
     * Detach the 'pdfable' behavior
     *
     * @param $controller
     */
    public function detachPdfableBehavior($controller)
    {
        $controller->detachBehavior('pdfable');
    }

    /**
     * Render a controller view with the pdfable extension (WkHtmlToPdf).
     * Attaches the pdfable behavior to the current controller and calls renderPdf.
     * @link: http://www.yiiframework.com/extension/pdfable/
     *
     * @param $controller
     * @param $view
     * @param array $data
     * @param null $filename
     * @param array $htmlToPdfPageOptions
     * @param array $htmlToPdfOptions
     */
    public function renderView($view, $data=array(),$controller=null,$filename=null, $htmlToPdfPageOptions = array(), $htmlToPdfOptions = array())
    {
        if(!isset($controller))
            $controller = Yii::app()->controller;

        $this->attachPdfableBehavior($controller,$htmlToPdfPageOptions,$htmlToPdfOptions);
        $controller->renderPdf($view,$data,array_merge($this->htmlToPdfPageOptions, $htmlToPdfPageOptions),$filename);
    }


    /**
     * Render a pdf from a url with the pdfable extension (WkHtmlToPdf)
     * Renders the current controllers url, if url not set.
     *
     * @link: http://www.yiiframework.com/extension/pdfable/
     *
     * @param null $url
     * @param array $urlParams
     * @param null $fileName download if isset
     * @param array $htmlToPdfPageOptions
     * @param array $htmlToPdfOptions
     * @throws CException
     */
    public function renderUrl($url=null, $urlParams=array(), $fileName=null,$htmlToPdfPageOptions = array(), $htmlToPdfOptions = array())
    {
        if (empty($this->pdfableExt))
            throw new CException('Extension pdfable not configured');

        if(!empty($url))
        {
            if(is_array($url) || (is_string($url) && strpos($url,'://') === false))
                $url = Yii::app()->createAbsoluteUrl($url,$urlParams);
        }
        else
        {
            $url=Yii::app()->controller->createAbsoluteUrl('',$urlParams);
        }

        $pdf = $this->createWkHtmlToPdf($htmlToPdfPageOptions, $htmlToPdfOptions);
        $pdf->addPage($url);
        $pdf->send($fileName);
    }

    /**
     * Check if a file is cached.
     *
     * @param $name
     * @return bool
     */
    public function isCached($name)
    {
        $this->_currentCacheFiles[$name] = null;

        if(empty($name))
            return false;

        return $this->getCachedFile($name,false) !== null;
    }

    /**
     * Flush the cache.
     *
     * @param string $pdfFileName
     */
    public function flushCache($pdfFileName='')
    {
        $this->_currentCacheFiles[$pdfFileName]=null;

        if(!empty($pdfFileName))
        {
            $file = $this->getPdfPath().DIRECTORY_SEPARATOR.$pdfFileName;
            if (file_exists($file))
                unlink($file);
        }
        else
            CFileHelper::removeDirectory($this->pdfPath);
    }


    /**
     * Check if the cache is enabled
     *
     * @return bool
     */
    public function isCacheEnabled()
    {
        return $this->cacheHours>=0;
    }

    /**
     * Disable the cache
     */
    public function disableCache()
    {
        $this->cacheHours=-1;
    }

    /**
     * Return null if cache is not enabled.
     * Create the cache file if not exists or is expired.
     *
     * @param $name
     * @return null|string
     * @throws CHttpException
     */
    public function getCachedFile($name,$createIfNotExists=true)
    {
        if(empty($name) || !$this->isCacheEnabled())
            return null;

        $name=basename($name);

        if(!empty($this->_currentCacheFiles[$name]))
            return $this->_currentCacheFiles[$name];

        $pdfPath=$this->getPdfPath();

        $file = $pdfPath.DIRECTORY_SEPARATOR.$name;
        if (!file_exists($file))
        {
            if($createIfNotExists)
            {
                if(empty($this->_pdfObject))
                    throw new CHttpException('No pdf instance created: Call one of the methods createTCPDF(),createFPDI() before output.');

                $this->_pdfObject->output($file,'F');
                if(!is_file($file))
                    throw new CHttpException('Unable to create output file: '.$file);

                return $file;
            }
            else
                return null;
        }

        if($this->cacheHours == 0) //cache never expires
            return $file;

        //check expired
        clearstatcache();
        $expired = filemtime($file) < mktime(date("H") - $this->cacheHours, date("i"), 0, date("m"), date("d"), date("y"));
        if($expired)
        {
            $this->flushCache($name);
            return $this->getCachedFile($name,$createIfNotExists);
        }

        $this->_currentCacheFiles[$name] = $file;
        return $file;
    }


    /**
     * Output an existings pdf file to the specified destination.
     *
     * @param $file
     * @param $name
     * @param string $dest
     * @return string
     * @throws CException
     */
    public function outputPdfFile($file,$name, $dest='I')
    {
        if(!is_file($file))
            throw new CException('Invalid cache file: '.$file);

        $dest = $this->getValidatedDestination($dest);

        if($dest=='F') //output as file = $cachedFile;
            return $file;

        $data = file_get_contents($file);

        if($dest=='S') //output as string
            return $data;

        //remove F if dest is FI, FD ... because the file exists
        if(strpos($dest,'F')===0)
            $dest = substr($dest,1);

        $name = preg_replace('/[\s]+/', '_', $name);
        $name = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $name);

        switch($dest)
        {
            case 'I': // Send PDF to the standard output
                if (php_sapi_name() != 'cli')
                {
                    // send output to a browser
                    header('Content-Type: application/pdf');
                    if (headers_sent())
                        throw new CException('Some data has already been output to browser, can\'t send PDF file');

                    header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
                    //header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
                    header('Pragma: public');
                    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                    header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
                    header('Content-Disposition: inline; filename="'.basename($name).'"');
                    TCPDF_STATIC::sendOutputData($data, strlen($data));
                }
                else
                    echo $data;
                break;

            case 'D': //Download
                Yii::app()->getRequest()->sendFile($name,$data,'application/pdf');
                break;

            case 'E':
                // return PDF as base64 mime multi-part email attachment (RFC 2045)
                $retval = 'Content-Type: application/pdf;'."\r\n";
                $retval .= ' name="'.$name.'"'."\r\n";
                $retval .= 'Content-Transfer-Encoding: base64'."\r\n";
                $retval .= 'Content-Disposition: attachment;'."\r\n";
                $retval .= ' filename="'.$name.'"'."\r\n\r\n";
                $retval .= chunk_split(base64_encode($data), 76, "\r\n");
                return $retval;

            default:
                throw new CException('Incorrect output destination: '.$dest);
        }
    }


    /**
     * Destination where to send the document
     *
     * @return bool
     */
    protected function getValidatedDestination($dest=null)
    {
        //Normalize parameters
        if (is_bool($dest))
            return $dest ? 'D' : 'F';

        if(empty($dest))
            $dest = 'I';

        $dest = strtoupper($dest);

        return $dest=='I' || // Send PDF to the standard output
        $dest=='D' || // download PDF as file
        $dest=='F' || // save PDF to a local file
        $dest=='FI' ||
        $dest=='FD' ||
        $dest=='E' || // return PDF as base64 mime multi-part email attachment (RFC 2045)
        $dest=='S' ? $dest : 'I';
    }


    /**
     * Translates an alias into a file path if the path includes dot.
     *
     * @param $path
     * @return mixed
     */
    public static function translatePath($path)
    {
        return strpos($path,'.') !== false ? Yii::getPathOfAlias($path) : realpath($path);
    }

}
