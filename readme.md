This extension simplifies the integration of the [TCPDF](http://www.tcpdf.org/ ""), [FPDI](http://www.setasign.com/products/fpdi/about/ "")
and optional the yii extension [pdfable](http://www.yiiframework.com/extension/pdfable/ "") into your application.

The combination with FPDI allows to read pages from existing pdf files and use them as templates to write into (letter headed paper,...).

The extension provides a **base document class** "EPDFFactoryDoc" where you can design your document without thinking about all the stuff around to integrate the libraries and instantiate the classes.
The **feature of caching** of created pdf documents is supported.

##Requirements

- PHP 5.3+
- Developed with Yii 1.1.14 (not tested with older versions, but should work too)
- [TCPDF](http://www.tcpdf.org/ "") not included.
- Optional: [pdfable](http://www.yiiframework.com/extension/pdfable/ "") not included.
- Included: [FPDI](http://www.setasign.com/products/fpdi/about/ "")


Download the latest pdfFactory version from [github](https://github.com/yii-joblo/pdffactory "").

##Installation

1. Extract the files into protected/extensions/pdffactory
2. Download [TCPDF](http://www.tcpdf.org/ "") and extract the library into protected/extensions/pdffactory/vendors/tcpdf.
3. Configure the application component EPdfFactory in your config file config/main.php

You can place the vendor libraries in another path, if you configure *tcpdfPath* and *fpdiPath*.

~~~
[php]
        'import' => array(
                ...
                'ext.pdffactory.*',
                'application.pdf.docs.*', //the path where you place the EPdfFactoryDoc classes
            ),

 		'components' => array(
         ...
         
        'pdfFactory'=>array(
            'class'=>'ext.pdffactory.EPdfFactory',

            //'tcpdfPath'=>'ext.pdffactory.vendors.tcpdf', //=default: the path to the tcpdf library
            //'fpdiPath'=>'ext.pdffactory.vendors.fpdi', //=default: the path to the fpdi library

            //the cache duration
            'cacheHours'=>5, //-1 = cache disabled, 0 = never expires, hours if >0

             //The alias path to the directory, where the pdf files should be created
            'pdfPath'=>'application.runtime.pdf',

            //The alias path to the *.pdf template files
            //'templatesPath'=>'application.pdf.templates', //= default

            //the params for the constructor of the TCPDF class  
            // see: http://www.tcpdf.org/doc/code/classTCPDF.html 
			'tcpdfOptions'=>array(
                  /* default values
                    'format'=>'A4',
                    'orientation'=>'P', //=Portrait or 'L' = landscape
                    'unit'=>'mm', //measure unit: mm, cm, inch, or point
                    'unicode'=>true,
                    'encoding'=>'UTF-8',
                    'diskcache'=>false,
                    'pdfa'=>false,
                   */
            )
        ),
    ),
~~~

The **configured attributes** above are **only the default values**. You can assign other values to these attributes before rendering a pdf.

You can use the [composer](https://getcomposer.org/ "") do download/install the components, a *composer.json* file is included.


##Usage

###Basic usage of TCPDF / FPDI instances

Working with the raw tcpdf / fpdi instances is **not the purpose of this extension.**
But if you need that, you can instantiate these classes like below.

~~~
[php]

 //create a TCPDF instance with the params from tcpdfOptions from config/main.php
 $pdf=Yii::app()->pdfFactory->getTCPDF(); 

 //use this instance like explained in [TCPDF examples](http://www.tcpdf.org/examples.php "") 
 $pdf->SetCreator(PDF_CREATOR);
 $pdf->SetAuthor('Nicola Asuni');
 $pdf->addPage();
 $pdf->write(0,'Hello world');
 ...
 $pdf->Output();

 //create a TCPDF instance with other tcpdfOptions than the configured default.
 $pdf=Yii::app()->pdfFactory->getTCPDF(array('format'=>'A5')); 
 

 //create a FPDI instance (always brigded mode so FPDI extends TCPDF)
 //see [FPDI](http://www.setasign.com/products/fpdi/about/ "") 
 $pdf=Yii::app()->pdfFactory->getFPDI(); //other options like above  
 $pdf->SetCreator(PDF_CREATOR);
 $pdf->SetAuthor('Nicola Asuni');

 //import the template
 $pdf->setSourceFile('...path to pdf template file...');
 $tplidx = $pdf->importPage(1);
 $pdf->addPage();
 $pdf->useTemplate($tplidx, 10, 10, 90);

 $pdf->write(0,'Hello world');
 ...
 $pdf->Output();  

~~~

###Using EPdfFactoryDoc

Create predefined classes in the configured import path for your pdf docclasses to output the pdf with a few lines in your controller action.

The minimum methods you have to override from the parent are 

- getPdfName()
- initDocInfo()
- renderPdf()

The renderPdf() must always start with the lines *$this->addPage() and $this->getPdf()*;

~~~
[php]

	class ProductPdf extends EPdfFactoryDoc
	{

   	 public function renderPdf()
     {
        $this->addPage(); 
	    $pdf = $this->getPdf();
        ...
     }

      ...
	}

~~~

But **don't call output() in this method.**


The EPdfFactoryDoc supports the methods *setData(), getData() and getDataItem()*.
Data is a simple array you assign before pdf output. You have access to the data inside the doc class.

The method *getPdf()* return the TCPDF or FPDI instance.
The type if instance depends on you have assigned a pdf template before output.


~~~
[php]

class ProductPdf extends EPdfFactoryDoc
{
    
    // the pdf name on creating the (cached) file or downloading.
    //always add the extension '.pdf'
    public function getPdfName()
    {
        return $this->getDataItem('model')->title) . '.pdf';
    }

    // the info assigned to the pdf document
    protected function initDocInfo()
    {
        $pdf = $this->getPdf();

        $pdf->SetTitle('My company');
        $pdf->SetSubject('Product description');
        $pdf->SetKeywords('x, y, z');
    }

    
    public function renderPdf()
    {
        $this->addPage();        
        $pdf = $this->getPdf();

        $pdf->SetFontSize(18);
        $model = $this->getDataItem('model');        
        $pdf->Write(0, 'Article No. ' . $model->id);
        //all code to render the pdf, but no output()
        ...
    } 

~~~

**More methods to override** - take a look at the source of EPdfFactoryDoc:

- initMargins()
- initFont()
- initHeader()
- initFooter()


Controller action:
Render a pdf if the GET param 'pdf' isset.

~~~
[php]

	public function actionProduct($id)
    {
        $model = Product::model()->findByPk($id); //load from db

        if(isset($_GET['pdf']))
        {
           $productPdf = ProductPdf::doc();
           $productPdf->setData(array('model'=>$model));
           $productPdf->output(); //destination param = 'I' = standard output to the browser
           //other destination params: 'F'=file, 'D'=download, 'S'=string ... see TCPDF docs  
           //$productPdf->output('D'); //enforce download
        }
        else
            $this->render('product',array('model'=>$model));
    }
~~~

If you want to **assign a pdf file as template** you can **override the init() method** of the EPdfFactoryDoc class,
**or you use the setTemplate() method after creating the doc**.

**If you assign a template on/after creating the doc, the internal created class on rendering will be a FPDI not a TCPDF.** So you don't have to think about FPDI or TCPDF. 

~~~
[php]

	class ProductPdf extends EPdfFactoryDoc
	{
	    
   	 	public function init()
	    {
	       $this->setTemplate('productsheet.pdf', 0, 0, 210); //with defining the area to print in 
	       //override the configured default
	       //$this->setTcpdfOptions(array('orientation'=>'L', ...);
	       
	    }

    ... 
    }

~~~

or to be more flexible by assigning the template in the controller action.

~~~
[php]

	public function actionProduct($id)
    {
        
		$model = Product::model()->findByPk($id); //load from db

        if(isset($_GET['pdf']))
        {
           $productPdf = ProductPdf::doc();
           $productPdf->setTemplate('productsheet1.pdf', 0, 0, 210); //maybe change by action params ...
           //$productPdf->setTcpdfOptions(array('orientation'=>'L', ...);
        ...
          
    }
~~~


##More examples

If you want to **mail your generated pdf**: 

~~~
[php]

	public function actionMailProduct($id)
    {
        $model = Product::model()->findByPk($id); //load from db

        $productPdf = ProductPdf::doc();
        $productPdf->setData(array('model'=>$model));
        $pdfFile=$productPdf->output('F'); //file saved as configured pdfPath/pdfName
        ... code to attach the file to your mailer and send mail
    }
~~~

**Multipage pdf**

If you want to render **different products into one pdf**:

~~~
[php]

	class ProductPdf extends EPdfFactoryDoc
	{

     //render a single page for each model
     protected function renderPage($model)
     {
        $this->addPage(); 
	    $pdf = $this->getPdf();

        ...
        $pdf->Write(0, 'Article No. ' . $model->id);
        ...
     }

   	 public function renderPdf()
     {
        foreach($this->getDataItem('models') as $model)        
          $this->renderPage($model);      
     }

      ...
	}
~~~


~~~
[php]

	public function actionDownloadProducts()
    {
        $models = Product::model()->findAll(); //findByAttributes ...

        $productPdf = ProductPdf::doc();
        $productPdf->setData(array('models'=>$models));
        $productPdf->output('D');         
    }
~~~


##Caching

If you have enabled the cache by configuring 'cacheHours' >= 0, a pdf file will be created after rendering:
path/name with the configured pdfPath as path and the result from getPdfName() as name.

So if you call *$productPdf->output('D')*, the cache file will be created if the file not exists or is expired.
On the next call, the pdf will not be rendered again, instead the cached file will be used to send to the browser.
The same for all other destinations: F,I,...

**Flushing the cache**

Use the method *flushCache()* if the data for generating a pdf has been changed.
For example in the method afterSave() of a model.
Or add it to a controller action *actionFlushPdfCache()*

~~~
[php]

	Yii::app()->pdfFactory->flushCache(); //all files
	Yii::app()->pdfFactory->flushCache('invoice.pdf'); //a single file within the configured pdfPath = cachePath

~~~


**More cache methods:**

- *isCached('invoice.pdf')* check if a file is cached
- *isCacheEnabled()* check if the cache is enabled
- *disableCache()* temporary disable the cache



##Extension pdfable

If you have installed the extension [pdfable](http://www.yiiframework.com/extension/pdfable/ ""),
you can configure and use this extension inside the pdfFactory application component.
The difference is, that **you don't have to add the pdfable action to a controller**, pdfFactory will do this.
You can render a view/url inside every controller you like.

Add the extension to the pdfFactory by adding the addional keys:
'pdfableExt','htmlToPdfOptions' and 'htmlToPdfPageOptions'
Take a look at the the docs of pdfable for the available options.

~~~
[php]

 		'components' => array(
         ...
         
        'pdfFactory'=>array(
            'class'=>'ext.pdffactory.EPdfFactory',
            ...
         'pdfableExt'=>'ext.pdfable',

         //the pdfable attributes
		 'htmlToPdfOptions' => array(
		        //'bin' => '/usr/bin/wkhtmltopdf',
		        // 'dpi'   => 600,
		    );

          //the pdfable page attributes
         '$htmlToPdfPageOptions' => array(
		        // 'page-size'         => 'A5',
                // 'user-style-sheet'  => Yii::getPathOfAlias('webroot').'/css/pdf.css',
		    );

    ),
~~~ 

Now you have the **methods renderUrl() and renderView() **available.
Caching is not supported when calling these methods.

Work with the WkHtmlToPdf instance by calling *$wkPdf = Yii::app()->pdfFactory->getWkHtmlToPdf();*

Usage in a controller:

~~~
[php]

	public function actionProduct($id)
    {
        
		$model = Product::model()->findByPk($id); //load from db

        if(isset($_GET['pdf']))
        {
           Yii::app()->pdfFactory->renderUrl(null,array('id'=>$id)); //the current request url
        }
        else
            $this->render('product',array('model'=>$model));   
          
    }
~~~

Or a other action method (maybe in another controller)

~~~
[php]

	public function actionRenderProduktPdf(id)
    {
       Yii::app()->pdfFactory->renderUrl('products/product',array('id'=>$id));
       //Yii::app()->pdfFactory->renderUrl('http://www.yiiframework.com/'); 
       ...

       OR
       $model = Product::model()->findByPk($id); //load from db
       Yii::app()->pdfFactory->renderView('product',array('model'=>$model));
          
    }
~~~


##Resources

- [pdfFactory on github](https://github.com/yii-joblo/pdffactory "")
- [TCPDF](http://www.tcpdf.org/ "")
- [FPDI](http://www.setasign.com/products/fpdi/about/ "")
- [pdfable](http://www.yiiframework.com/extension/pdfable/ "")


##Changelog

- 1.0.1 bugfixes: issues when using multiple doc classes for output; multipage FPDI templates
- 1.0.0 initial release


