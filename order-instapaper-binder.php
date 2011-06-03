<?php

session_start();

require_once('pdfcrowd.php');
require_once('config.php');
require_once('rest_client.php');

try
{   
    // create an API client instance
    $client = new Pdfcrowd(PDFCROWD_USERNAME,PDFCROWD_KEY);

	// Set us some margins
	$client->setHorizontalMargin("0.5in");
	$client->setVerticalMargin("0.5in");
	
	// gonna set to 35 page limit for now;
	$client->setMaxPages("35");

    // convert a web page and store the generated PDF into a $pdf variable
    $pdf = $client->convertURI('http://working.laneworks.net/instapaperbinder/pull-instapaper-entries.php');
    
	$Save_Filename = "instapaper-binder.pdf";
	
	$Save_URL = "http://working.laneworks.net/instapaperbinder/" . $Save_Filename;
	
	$fh = fopen($Save_Filename, "w");
	fputs($fh,$pdf,strlen($pdf));
	fclose ($fh);    

	// Create a Mimeo REST Client
	$rest = new RESTclient(MIMEO_ROOTURL,MIMEO_USER,MIMEO_PASSWORD);
	
	$template = "custom";
	$url = "OrderService/NewProduct?template=" . $template . "&documentTemplateId=" . MIMEO_BINDER_DOCUMENT;
	
	$rest->createRequest($url,"GET","");
	$rest->sendRequest();
	$output = $rest->getResponseBody();

	//Store Order Template for Manipulation
	$xml = new SimpleXMLElement($output);
	$OrderXML = new SimpleXMLElement($output);
	
	//Set these Document with Our Dynamically Generated PDF from Instapaper
	$xml->Product->Content->DocumentSection[0]->Source = $Save_URL;
	$xml->Product->Content->DocumentSection[0]->Range = "[1,35]";
	
	// Set Our Shipping Information
	$xml->Addresses->RecipientAddress->CompanyName = "Mimeo";
	$xml->Addresses->RecipientAddress->Name = "Kin Lane";
	$xml->Addresses->RecipientAddress->FirstName = "Kin";
	$xml->Addresses->RecipientAddress->LastName = "Lane";
	$xml->Addresses->RecipientAddress->CareOf = "Kin Lane";
	$xml->Addresses->RecipientAddress->Street = "415 Kourt Drive";
	$xml->Addresses->RecipientAddress->ApartmentOrSuite = " ";
	$xml->Addresses->RecipientAddress->City = "Eugene";
	$xml->Addresses->RecipientAddress->StateOrProvince = "OR";
	$xml->Addresses->RecipientAddress->PostalCode = "97404";
	$xml->Addresses->RecipientAddress->Country = "US";
	$xml->Addresses->RecipientAddress->TelephoneNumber = "541-913-2328";
	$xml->Addresses->RecipientAddress->Email = "kin.lane@mimeo.com";
	$xml->Addresses->RecipientAddress->IsResidential = "false";
	
	// Set the order quantity
	$xml->Details->OrderQuantity = "1";
	
	//Set the XML to Send Back
	$SendXML = $xml->asXML();

	$rest = new RESTclient(MIMEO_ROOTURL,MIMEO_USER,MIMEO_PASSWORD);
	$url = "OrderService/GetShippingOptions";
	$rest->createRequest($url,"POST","");
	$rest->setHeader("Content-Type","application/xml");
	$rest->setBody($SendXML);
	$rest->sendRequest();
	$shipopts = $rest->getResponseBody();
	
	//Store Order Template for Manipulation
	$xml = new SimpleXMLElement($shipopts);
	
	$ShippingChoice = "";
	
	foreach ($xml->Details->ShippingOptions->ShippingOption as $ShippingOption) {
	
		echo "<br />ID: " . $ShippingOption->Id . "<br />";
		//I'm just going to set the shipping choice to each one, we'll endup with last
		$ShippingChoice = $ShippingOption->Id;
		echo "Name: " . $ShippingOption->Name . "<br />";
		echo "Charge: " . $ShippingOption->Charge . "<br />";
		echo "DeliveryDate: " . $ShippingOption->DeliveryDate . "<br /><br />";
		
		}
		
	///Set the order ShippingChoice to whatever the last choice was
	$xml->Details->ShippingChoice = $ShippingChoice;
	
	$xml->Details->ShippingOptions = "";
	
	//Set the XML to Send Back
	$SendXML = $xml->asXML();

	$rest = new RESTclient(MIMEO_ROOTURL,MIMEO_USER,MIMEO_PASSWORD);
	$url = "OrderService/GetQuote";
	$rest->createRequest($url,"POST","");
	$rest->setHeader("Content-Type","application/xml");
	$rest->setBody($SendXML);
	$rest->sendRequest();
	$orderquote = $rest->getResponseBody();

	//Store Order Template for Manipulation
	$xml = new SimpleXMLElement($orderquote);
	
	echo "ShippingOptions: " . $xml->Details->ShippingOptions . "<br />";
	echo "ShippingChoice: " . $xml->Details->ShippingChoice . "<br />";
	
	echo "ProductPrice: " . $xml->Details->ProductPrice . "<br />";
	echo "ShippingPrice: " . $xml->Details->ShippingPrice . "<br />";
	echo "HandlingPrice: " . $xml->Details->HandlingPrice . "<br />";
	echo "TaxPrice: " . $xml->Details->TaxPrice . "<br />";
	echo "TotalPrice: " . $xml->Details->TotalPrice . "<br />";
	echo "OrderId: " . $xml->Details->OrderId . "<br />";
	echo "OrderQuantity: " . $xml->Details->OrderQuantity . "<br />";
	
	//Set the XML to Send Back
	$SendXML = $xml->asXML();

	$rest = new RESTclient(MIMEO_ROOTURL,MIMEO_USER,MIMEO_PASSWORD);
	$url = "OrderService/PlaceOrder";
	$rest->createRequest($url,"POST","");
	$rest->setHeader("Content-Type","application/xml");
	$rest->setBody($SendXML);
	$rest->sendRequest();
	$orderinfo = $rest->getResponseBody();

	$xml = new SimpleXMLElement($orderinfo);
	$Order_ID = $xml->OrderFriendlyId;
	
	echo "Order ID: " . $Order_ID . "<br />";


}
catch(PdfcrowdException $e)
{
    echo "Pdfcrowd Error: " . $e->getMessage();
}