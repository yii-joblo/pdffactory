<?php
/**
 * Class EPdfFactoryDoc.
 *
 * The base class for pdf docs.
 * Simplifies generating a pdf documents.
 *
 * Implement a pdf doc class by overriding
 *  - getPdfName()
 *  - renderPdf()
 *  - initDocInfo()
 *
 *  optional:
 *  - init()
 *  - initMargins(), initFont(), initHeader(), initFooter()
 *
 * Use setData() before rendering the pdf and use getData(), getDataItem() in the method renderPdf().
 *
 * @author Joe Blocher <yii@myticket.at>
 * @copyright 2014 myticket it-solutions gmbh
 * @license New BSD License
 * @category User Interface
 * @package joblo/pdffactory
 * @version 1.0.0
 */
abstract class EPdfFactoryDoc
{
    protected $_pdf;
    protected $_tplPageCount;
    protected $_currentTplIndex;
    protected $_tplName;
    protected $_tplX;
    protected $_tplY;
    protected $_tplW;
    protected $_tplH;
    protected $_data;
    protected $_tcpdfOptions=array();

    /**
     * The constructor calls the init() method.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Static method to instantiate a EPdfFactoryDoc class.
     *
     * @return EPdfFactoryDoc
     */
    public static function doc()
    {
        $class=get_called_class();
        return new $class;
    }

    /**
     * The name of the pdf document used on creating the pdf file or on download.
     *
     * @return string
     */
    public function getPdfName()
    {
        return 'document.pdf';
    }

    /**
     * Implement the rendering of the pdf.
     * The two first lines must be:
     *  $this->addPage();
     *  $pdf = $this->getPdf(); //TCPDF of FPDI instance
     *
     * Then render the page by using the TCPDF/FPDI methods of the pdf object.
     * Use the methods getData(), getDataItem() to work with array data assigned by setData() before rendering.
     *
     */
    public function renderPdf()
    {
        /*
         $this->addPage();
         $pdf = $this->getPdf(); the instance of TCPDF or FPDI (extends TPDF)
         $pdf->SetFontSize(18);
          ...
         $caption = $this->getDataItem('caption');
         $pdf->write(0,$caption);
          ...
        */
    }

    /**
     * Generate an output the pdf to the specified destination.
     * Uses the cached file if the cache is enabled and the file is not expired,
     * otherwise calls renderPdf().
     *
     * Possible values for $dest:
     * 'I' = standard output (open in the browser...)
     * 'D' = download
     * 'F' = only create the file in the
     * 'E' = PDF as base64 mime multi-part email attachment (RFC 2045)
     * 'S' = the pdf as string
     *
     * Allowed combinations: 'FI' and 'FD'
     *
     * @param string $dest
     * @param string $name
     * @return mixed
     */
    public function output($dest='I',$name=null)
    {
        if(empty($name))
            $name=$this->getPdfName();
        $pdfFactory = Yii::app()->pdfFactory;
        if(!$pdfFactory->isCached($name))
            $this->renderPdf();

        $pdfFactory->setPdfObject($this->getPdf());
        return $pdfFactory->output($name,$dest);
    }

    /**
     * Override this method to assign a FPDI template
     * or change the attributes of the EPdfFactory application component configured in config/main.php.
     * Don't init docinfo, margins, font, header, footer here, this will be done by initDoc() on getPdf.
     */
    public function init()
    {
        /*
         $this->setTemplate('invoice1.pdf', 0, 0, 210); //must be called before the first time $this->getPdf() is called.
         $this->setTcpdfOptions(
          array(
              'format'=>'A5',
              'orientation'=>'L',
             )
          );

          Yii::app()->pdf->pdfPath= ...
          Yii::app()->pdf->disableCache();
        */
    }


    /**
     * Initialize the docinfo, margins, font, header and footer.
     * Override the initX methods to set docinfo, margins ...
     */
    protected function initDoc()
    {
        $this->initDocInfo();
        $this->initMargins();
        $this->initFont();
        $this->initHeader();
        $this->initFooter();
    }

    /**
     * Override in inherited classes to assign information about author, ... to the pdf document.
     * @see documentation of TCPDF.
     */
    protected function initDocInfo()
    {
        $pdf = $this->getPdf();
        $pdf->SetCreator('Yii PdfFactory');
        $pdf->SetAuthor(Yii::app()->user->isGuest ? 'Yii' : Yii::app()->user->name);
    }

    /**
     * Don't assign a header by default
     */
    protected function initHeader()
    {
        $this->getPdf()->setPrintHeader(false);
    }

    /**
     * Don't assign a footer by default
     */
    protected function initFooter()
    {
        $this->getPdf()->setPrintFooter(false);
    }

    /**
     * Set the default configured font PDF_FONT_MONOSPACED of TCPDF (= courier).
     * @see vendors/tcpdf/config/tcpdf_config.php
     */
    protected function initFont()
    {
        $this->getPdf()->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    }

    /**
     * Assign the default margins and autopagebreak=true.
     *
     * The const are defined in vendors/tcpdf/config/tcpdf_config.php
     */
    protected function initMargins()
    {
        $pdf = $this->getPdf();
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    }

    /**
     * Get the TCPDF instance.
     * If a template was assigned by setTemplate(),
     * the result is a FPDI (extends TCPDF), a TCPDF instance otherwise.
     *
     * @return TCPDF or FPDI instance
     */
    public function getPdf()
    {
        if($this->_pdf === null)
        {
            if(!empty($this->_tplName))
            {
                $this->_pdf = Yii::app()->pdfFactory->getFPDI($this->_tcpdfOptions);
                $this->importTemplate();
            }
            else
                $this->_pdf = Yii::app()->pdfFactory->getTCPDF($this->_tcpdfOptions);

            $this->initDoc();
        }

        return $this->_pdf;
    }

    /**
     * Assign a pdf template before calling $this->getPdf() the first time.
     * In this case, a FPDI instance will be created instead of a TCPDF instance on getPdf().
     *
     * @param $name
     * @param null $tplX
     * @param null $tplY
     * @param int $tplW
     * @param int $tplH
     */
    public function setTemplate($name,$tplX = null, $tplY = null, $tplW = 0, $tplH = 0)
    {
        $this->_tplName = $name;
        $this->_tplX = $tplX;
        $this->_tplY = $tplY;
        $this->_tplW = $tplW;
        $this->_tplH = $tplH;
    }

    /**
     * Use this method in the init() method.
     * The assigned tcpdfOptions will be used on creating the TCPDF instance when calling getPdf() the first time.
     *
     * @param array $tcpdfOptions
     */
    public function setTcpdfOptions($tcpdfOptions)
    {
        $this->_tcpdfOptions = $tcpdfOptions;
    }

    /**
     * Assign external array data before rendering the page.
     *
     * @param array $data
     */
    public function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Use this method in renderPdf() to render items assigned by setData.
     *
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Get an item of the data array specified by the key.
     *
     * @param $key
     * @return null
     */
    public function getDataItem($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    /**
     * Set an item of the data array specified by the key.
     *
     * @param $name
     * @param $value
     */
    public function setDataItem($name,$value)
    {
        if(!isset($this->_data))
            $this->_data = array();
        $this->_data[$name]=$value;
    }

    /**
     * Get the current used page index of the templated on rendering, if the template has multiple pages.
     *
     * @return mixed
     */
    public function getCurrentTplIndex()
    {
        return $this->_currentTplIndex;
    }

    /**
     * The name of the assigned template.
     *
     * @return string
     */
    public function getTplName()
    {
        return $this->_tplName;
    }

    /**
     * The pagecount of the assigned template.
     * @return mixed
     */
    public function getTplPageCount()
    {
        return $this->_tplPageCount;
    }

    /**
     * Always use this method as first line in renderPdf() to add a page.
     * Adds a page and assigns the specified page by index of the template if the pdf instance is a FPDI.
     *
     * @param null $tplPageNo
     */
    public function addPage($tplPageNo=null)
    {
        $this->getPdf()->addPage();
        $this->useTemplatePage($tplPageNo);
    }

    /**
     * Import the template for FPDI.
     * Checks the current template page index.
     *
     * @return int|null
     */
    protected function importTemplate()
    {
        if ($this->getPdf() instanceof FPDI && !empty($this->_tplName))
        {
            $tplFile = Yii::app()->pdfFactory->getTemplatesPath() . DIRECTORY_SEPARATOR . $this->_tplName;
            $this->_tplPageCount = $this->_pdf->setSourceFile($tplFile);
            if($this->_tplPageCount>0)
            {
                for($i=1;$i<=$this->_tplPageCount;$i++)
                    $this->_pdf->importPage($i);

                return $this->_currentTplIndex = 1; //set to the first page of the template
            }
        }
        return $this->_currentTplIndex = null;
    }

    /**
     * Called by addPage to assign the current page of the template.
     * Increments the index of the current template page or set to 1.
     * Checks the max width and height of the template.
     *
     * @param null $tplPageNo
     */
    public function useTemplatePage($tplPageNo=null)
    {
        if ($this->getPdf() instanceof FPDI)
        {
            if(is_null($tplPageNo))
                $tplPageNo = $this->_currentTplIndex;

            if(!is_null($tplPageNo) && $tplPageNo>=0 && $tplPageNo<=$this->_tplPageCount)
            {
                $dim = $this->_pdf->useTemplate($tplPageNo, $this->_tplX, $this->_tplY, $this->_tplW,$this->_tplH);

                if(isset($dim['w']) && $dim['w']>$this->_tplW)
                    $this->_tplW=$dim['w'];

                if(isset($dim['h']) && $dim['h']>$this->_tplH)
                    $this->_tplH=$dim['h'];

                if($this->_currentTplIndex<$this->_tplPageCount)
                    $this->_currentTplIndex++;
                else
                    $this->_currentTplIndex=1;
            }
        }
    }
}