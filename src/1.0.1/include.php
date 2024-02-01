<?
global $DB, $MESS, $APPLICATION;
IncludeModuleLangFile(__FILE__);

CModule::AddAutoloadClasses(
	"imyie.quantityinc",
	array(
		"CIMYIEQuantityInc" => "classes/general/quantityinc.php",
	)
);