<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Maps\MapCapabilitiesLoader;
use Tests\Base\FunctionalTestCase;

class MapCapabilitiesLoaderTest extends FunctionalTestCase
{
    /**
     * @var MapCapabilitiesLoader
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = static::$container->get(MapCapabilitiesLoader::class);
    }

    /**
     * @dataProvider evaluateCapabilitiesDataProvider
     */
    public function testEvaluateCapabilities(string $xml, string $expectedType): void
    {
        $capabilities = $this->sut->evaluateCapabilitiesXML($xml);

        self::assertEquals($expectedType, $capabilities->getType());
    }

    /**
     * @return string[][]
     */
    public function evaluateCapabilitiesDataProvider(): array
    {
        return [
            [
                <<<WMS_XML
<?xml version="1.0" ?>
<WMS_Capabilities xmlns="http://www.opengis.net/wms" xmlns:sld="http://www.opengis.net/sld"
                  xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:inspire_common="http://inspire.ec.europa.eu/schemas/common/1.0"
                  xmlns:inspire_vs="http://inspire.ec.europa.eu/schemas/inspire_vs/1.0" version="1.3.0"
                  xsi:schemaLocation="http://www.opengis.net/wms http://schemas.opengis.net/wms/1.3.0/capabilities_1_3_0.xsd http://www.opengis.net/sld http://schemas.opengis.net/sld/1.1.0/sld_capabilities.xsd http://inspire.ec.europa.eu/schemas/inspire_vs/1.0 http://inspire.ec.europa.eu/schemas/inspire_vs/1.0/inspire_vs.xsd">
    <Service>
        <Name>WMS</Name>
        <Title>Web Map Service WebAtlasDE</Title>
        <Abstract>Der WMS WebAtlasDE hat als Datengrundlage die amtlichen Daten der Digitalen Landschaftsmodelle (DLM),
            die Hauskoordinaten (HK) sowie die Hausumringe (HU). Die Darstellung beruht auf einer bundesweit
            einheitlichen Definition des Web-Signaturenkataloges (Web-SK) der AdV.
        </Abstract>
        <KeywordList>
            <Keyword>WMS</Keyword>
            <Keyword>ViewService</Keyword>
            <Keyword>INSPIRE:ViewService</Keyword>
            <Keyword>INSPIRE</Keyword>
            <Keyword>Bund</Keyword>
            <Keyword>BKG</Keyword>
            <Keyword>Bundesamt für Kartographie und Geodäsie</Keyword>
            <Keyword>WMS_WebAtlasDE_palette_rgb_halbton</Keyword>
            <Keyword>Basis-DLM</Keyword>
            <Keyword>DLM50</Keyword>
            <Keyword>DLM250</Keyword>
            <Keyword>DLM1000</Keyword>
            <Keyword>HU</Keyword>
            <Keyword>HK</Keyword>
            <Keyword>Digitale Landschaftsmodelle</Keyword>
            <Keyword>Hausumringe</Keyword>
            <Keyword>Hauskoordinaten</Keyword>
            <Keyword>WebAtlasDE</Keyword>
            <Keyword>Web-SK</Keyword>
            <Keyword>Geografische Bezeichnungen</Keyword>
            <Keyword>Verwaltungseinheiten</Keyword>
            <Keyword>Adressen</Keyword>
            <Keyword>Bodenbedeckung</Keyword>
            <Keyword>Bodennutzung</Keyword>
            <Keyword>Tatsächliche Nutzung</Keyword>
            <Keyword>Gewässer</Keyword>
            <Keyword>Gewässernetz</Keyword>
            <Keyword>Verkehrsnetz</Keyword>
            <Keyword>Gebäude</Keyword>
            <Keyword>Verkehr</Keyword>
            <Keyword>Vegetation</Keyword>
            <Keyword>Vegetationsflächen</Keyword>
            <Keyword>Siedlung</Keyword>
            <Keyword>Siedlungsflächen</Keyword>
            <Keyword vocabulary="ISO">infoMapAccessService</Keyword>
        </KeywordList>
        <OnlineResource xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="http://www.geodatenzentrum.de"/>
        <ContactInformation>
            <ContactPersonPrimary>
                <ContactPerson></ContactPerson>
                <ContactOrganization>Dienstleistungszentrum des Bundes für Geoinformation und Geodäsie (Zentrale Stelle
                    Geotopographie der AdV)
                </ContactOrganization>
            </ContactPersonPrimary>
            <ContactPosition>Technischer Administrator</ContactPosition>
            <ContactAddress>
                <AddressType>postal</AddressType>
                <Address>Karl-Rothe-Str. 10 - 14</Address>
                <City>Leipzig</City>
                <StateOrProvince></StateOrProvince>
                <PostCode>04105</PostCode>
                <Country>Deutschland</Country>
            </ContactAddress>
            <ContactVoiceTelephone>+49 (0) 341 5634 333</ContactVoiceTelephone>
            <ContactFacsimileTelephone>+49 (0) 341 5634 415</ContactFacsimileTelephone>
            <ContactElectronicMailAddress>dlz@bkg.bund.de</ContactElectronicMailAddress>
        </ContactInformation>
        <Fees>Die Daten sind urheberrechtlich geschützt. Je nach Nutzung werden sie entgeltfrei oder gegen Entgelt zur
            Verfügung gestellt. Für den Erwerb von Nutzungsrechten wenden Sie sich bitte an die Zentrale Stelle
            Geotopographie der AdV/Dienstleistungszentrum des Bundesamtes für Kartographie und Geodäsie:
            https://gdz.bkg.bund.de/ Der Quellenvermerk ist zu beachten. | Quellenvermerk: &amp;copy; GeoBasis-DE / BKG
            &amp;lt;Jahr&amp;gt;
        </Fees>
        <AccessConstraints>Die Daten sind lizenzpflichtig.</AccessConstraints>
        <MaxWidth>6000</MaxWidth>
        <MaxHeight>6000</MaxHeight>
    </Service>
    <Capability>
        <Request>
            <GetCapabilities>
                <Format>text/xml</Format>
                <DCPType>
                    <HTTP>
                        <Get>
                            <OnlineResource
                                    xlink:href="https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?"/>
                        </Get>
                    </HTTP>
                </DCPType>
            </GetCapabilities>
            <GetMap>
                <Format>image/jpeg</Format>
                <Format>image/png</Format>
                <Format>image/png8</Format>
                <Format>image/png24</Format>
                <Format>image/png32</Format>
                <Format>image/tiff</Format>
                <Format>image/gif</Format>
                <DCPType>
                    <HTTP>
                        <Get>
                            <OnlineResource
                                    xlink:href="https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?"/>
                        </Get>
                    </HTTP>
                </DCPType>
            </GetMap>
            <GetFeatureInfo>
                <Format>text/plain</Format>
                <Format>text/html</Format>
                <Format>text/xml</Format>
                <DCPType>
                    <HTTP>
                        <Get>
                            <OnlineResource
                                    xlink:href="https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?"/>
                        </Get>
                    </HTTP>
                </DCPType>
            </GetFeatureInfo>
            <sld:GetLegendGraphic>
                <Format>image/jpeg</Format>
                <Format>image/png</Format>
                <Format>image/png8</Format>
                <Format>image/png24</Format>
                <Format>image/png32</Format>
                <Format>image/tiff</Format>
                <Format>image/gif</Format>
                <DCPType>
                    <HTTP>
                        <Get>
                            <OnlineResource
                                    xlink:href="https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?"/>
                        </Get>
                    </HTTP>
                </DCPType>
            </sld:GetLegendGraphic>
        </Request>
        <Exception>
            <Format>XML</Format>
            <Format>INIMAGE</Format>
            <Format>BLANK</Format>
        </Exception>
        <inspire_vs:ExtendedCapabilities>
            <inspire_common:MetadataUrl>
                <inspire_common:URL>http://mis.bkg.bund.de/geonetwork/srv/eng/csw?REQUEST=GetRecordById&amp;SERVICE=CSW&amp;VERSION=2.0.2&amp;OutputSchema=http://www.isotc211.org/2005/gmd&amp;elementSetName=full&amp;ID=8658f658-678a-40db-aeb4-20bd9966813c</inspire_common:URL>
                <inspire_common:MediaType>application/vnd.ogc.csw.GetRecordByIdResponse_xml</inspire_common:MediaType>
            </inspire_common:MetadataUrl>
            <inspire_common:SupportedLanguages>
                <inspire_common:DefaultLanguage>
                    <inspire_common:Language>ger</inspire_common:Language>
                </inspire_common:DefaultLanguage>
            </inspire_common:SupportedLanguages>
            <inspire_common:ResponseLanguage>
                <inspire_common:Language>ger</inspire_common:Language>
            </inspire_common:ResponseLanguage>
        </inspire_vs:ExtendedCapabilities>
        <Layer>
            <Title>WebAtlasDE Layers</Title>
            <CRS>CRS:84</CRS>
            <CRS>EPSG:4326</CRS>
            <CRS>EPSG:25832</CRS>
            <CRS>EPSG:25833</CRS>
            <CRS>EPSG:32632</CRS>
            <CRS>EPSG:32633</CRS>
            <CRS>EPSG:4647</CRS>
            <CRS>EPSG:5650</CRS>
            <CRS>EPSG:31462</CRS>
            <CRS>EPSG:31463</CRS>
            <CRS>EPSG:31464</CRS>
            <CRS>EPSG:31465</CRS>
            <CRS>EPSG:31466</CRS>
            <CRS>EPSG:31467</CRS>
            <CRS>EPSG:31468</CRS>
            <CRS>EPSG:31469</CRS>
            <CRS>EPSG:5676</CRS>
            <CRS>EPSG:5677</CRS>
            <CRS>EPSG:5678</CRS>
            <CRS>EPSG:5679</CRS>
            <CRS>EPSG:2397</CRS>
            <CRS>EPSG:2398</CRS>
            <CRS>EPSG:2399</CRS>
            <CRS>EPSG:3857</CRS>
            <CRS>EPSG:900913</CRS>
            <CRS>EPSG:102100</CRS>
            <CRS>EPSG:4258</CRS>
            <CRS>EPSG:3034</CRS>
            <CRS>EPSG:3035</CRS>
            <CRS>EPSG:3044</CRS>
            <CRS>EPSG:3045</CRS>
            <CRS>EPSG:4839</CRS>
            <CRS>EPSG:3068</CRS>
            <EX_GeographicBoundingBox>
                <westBoundLongitude>0.105946948013</westBoundLongitude>
                <eastBoundLongitude>20.4488892245</eastBoundLongitude>
                <southBoundLatitude>45.2375426953</southBoundLatitude>
                <northBoundLatitude>56.8478734515</northBoundLatitude>
            </EX_GeographicBoundingBox>
            <BoundingBox CRS="CRS:84" minx="0.105946948013" miny="45.2375426953" maxx="20.4488892245"
                         maxy="56.8478734515"/>
            <BoundingBox CRS="EPSG:31467" minx="5050382.78746" miny="2953714.44984" maxx="6303085.58158"
                         maxy="4206408.14968"/>
            <BoundingBox CRS="EPSG:31466" minx="5034349.26604" miny="2137655.8341" maxx="6338240.22575"
                         maxy="3441878.73386"/>
            <BoundingBox CRS="EPSG:31465" minx="4483978.17542" miny="5015209.71611" maxx="5836880.71826"
                         maxy="6367405.54244"/>
            <BoundingBox CRS="EPSG:31464" minx="3718816.26917" miny="5028368.28237" maxx="5021927.1702"
                         maxy="6331143.97685"/>
            <BoundingBox CRS="EPSG:31463" minx="2953714.44984" miny="5050382.78746" maxx="4206408.14968"
                         maxy="6303085.58158"/>
            <BoundingBox CRS="EPSG:31462" minx="2137655.8341" miny="5034349.26604" maxx="3441878.73386"
                         maxy="6338240.22575"/>
            <BoundingBox CRS="EPSG:31469" minx="5015209.71611" miny="4483978.17542" maxx="6367405.54244"
                         maxy="5836880.71826"/>
            <BoundingBox CRS="EPSG:31468" minx="5028368.28237" miny="3718816.26917" maxx="6331143.97685"
                         maxy="5021927.1702"/>
            <BoundingBox CRS="EPSG:4839" minx="-611167.698897" miny="-665032.749516" maxx="661756.548678"
                         maxy="615635.445755"/>
            <BoundingBox CRS="CRS:84" minx="0.105946948013" miny="45.2375426953" maxx="20.4488892245"
                         maxy="56.8478734515"/>
            <BoundingBox CRS="EPSG:3857" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                         maxy="7729088.68047"/>
            <BoundingBox CRS="EPSG:4647" minx="31953866.8307" miny="5048875.26301" maxx="33206211.0922"
                         maxy="6301219.54"/>
            <BoundingBox CRS="EPSG:3034" minx="2105849.72282" miny="3395314.92817" maxx="3328080.90059"
                         maxy="4625140.43382"/>
            <BoundingBox CRS="EPSG:3035" minx="2492075.69943" miny="3696700.38021" maxx="3756694.61622"
                         maxy="4965316.69602"/>
            <BoundingBox CRS="EPSG:25833" minx="-515738.4438" miny="5013712.00055" maxx="836787.377131"
                         maxy="6365521.90145"/>
            <BoundingBox CRS="EPSG:25832" minx="-46133.17" miny="5048875.26858" maxx="1206211.10142" maxy="6301219.54"/>
            <BoundingBox CRS="EPSG:2398" minx="5029021.86989" miny="3718843.83221" maxx="6331953.40417"
                         maxy="5022120.48358"/>
            <BoundingBox CRS="EPSG:32632" minx="-46133.1693302" miny="5048875.26313" maxx="1206211.09221"
                         maxy="6301219.54012"/>
            <BoundingBox CRS="EPSG:5678" minx="3718816.26917" miny="5028368.28237" maxx="5021927.1702"
                         maxy="6331143.97685"/>
            <BoundingBox CRS="EPSG:5679" minx="4483978.17542" miny="5015209.71611" maxx="5836880.71826"
                         maxy="6367405.54244"/>
            <BoundingBox CRS="EPSG:5676" minx="2137655.8341" miny="5034349.26604" maxx="3441878.73386"
                         maxy="6338240.22575"/>
            <BoundingBox CRS="EPSG:5677" minx="2953714.44984" miny="5050382.78746" maxx="4206408.14968"
                         maxy="6303085.58158"/>
            <BoundingBox CRS="EPSG:900913" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                         maxy="7729088.68047"/>
            <BoundingBox CRS="EPSG:5650" minx="32484261.5562" miny="5013712.00055" maxx="33836787.3771"
                         maxy="6365521.90145"/>
            <BoundingBox CRS="EPSG:2399" minx="5015856.7844" miny="4483976.0201" maxx="6368214.11758"
                         maxy="5837049.62448"/>
            <BoundingBox CRS="EPSG:2397" minx="5051043.35369" miny="2953771.7567" maxx="6303897.3097"
                         maxy="4206626.18653"/>
            <BoundingBox CRS="EPSG:32633" minx="-515738.443791" miny="5013712.00067" maxx="836787.377127"
                         maxy="6365521.90158"/>
            <BoundingBox CRS="EPSG:4326" minx="45.2375426953" miny="0.105946948013" maxx="56.8478734515"
                         maxy="20.4488892245"/>
            <BoundingBox CRS="EPSG:4258" minx="45.2375426953" miny="0.105946948013" maxx="56.8478734515"
                         maxy="20.4488892245"/>
            <BoundingBox CRS="EPSG:3068" minx="-779191.191332" miny="-865557.497855" maxx="550464.661196"
                         maxy="461310.425926"/>
            <BoundingBox CRS="EPSG:3045" minx="5013712.00055" miny="-515738.4438" maxx="6365521.90145"
                         maxy="836787.377131"/>
            <BoundingBox CRS="EPSG:3044" minx="5048875.26301" miny="-46133.1693348" maxx="6301219.54"
                         maxy="1206211.09222"/>
            <BoundingBox CRS="EPSG:102100" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                         maxy="7729088.68047"/>
            <Layer>
                <Name>webatlasde</Name>
                <Title>WebAtlasDE</Title>
                <Abstract>Kartenbild WebAtlasDE</Abstract>
                <EX_GeographicBoundingBox>
                    <westBoundLongitude>0.105946948013</westBoundLongitude>
                    <eastBoundLongitude>20.4488892245</eastBoundLongitude>
                    <southBoundLatitude>45.2375426953</southBoundLatitude>
                    <northBoundLatitude>56.8478734515</northBoundLatitude>
                </EX_GeographicBoundingBox>
                <BoundingBox CRS="CRS:84" minx="0.105946948013" miny="45.2375426953" maxx="20.4488892245"
                             maxy="56.8478734515"/>
                <BoundingBox CRS="EPSG:31467" minx="5050382.78746" miny="2953714.44984" maxx="6303085.58158"
                             maxy="4206408.14968"/>
                <BoundingBox CRS="EPSG:31466" minx="5034349.26604" miny="2137655.8341" maxx="6338240.22575"
                             maxy="3441878.73386"/>
                <BoundingBox CRS="EPSG:31465" minx="4483978.17542" miny="5015209.71611" maxx="5836880.71826"
                             maxy="6367405.54244"/>
                <BoundingBox CRS="EPSG:31464" minx="3718816.26917" miny="5028368.28237" maxx="5021927.1702"
                             maxy="6331143.97685"/>
                <BoundingBox CRS="EPSG:31463" minx="2953714.44984" miny="5050382.78746" maxx="4206408.14968"
                             maxy="6303085.58158"/>
                <BoundingBox CRS="EPSG:31462" minx="2137655.8341" miny="5034349.26604" maxx="3441878.73386"
                             maxy="6338240.22575"/>
                <BoundingBox CRS="EPSG:31469" minx="5015209.71611" miny="4483978.17542" maxx="6367405.54244"
                             maxy="5836880.71826"/>
                <BoundingBox CRS="EPSG:31468" minx="5028368.28237" miny="3718816.26917" maxx="6331143.97685"
                             maxy="5021927.1702"/>
                <BoundingBox CRS="EPSG:4839" minx="-611167.698897" miny="-665032.749516" maxx="661756.548678"
                             maxy="615635.445755"/>
                <BoundingBox CRS="CRS:84" minx="0.105946948013" miny="45.2375426953" maxx="20.4488892245"
                             maxy="56.8478734515"/>
                <BoundingBox CRS="EPSG:3857" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                             maxy="7729088.68047"/>
                <BoundingBox CRS="EPSG:4647" minx="31953866.8307" miny="5048875.26301" maxx="33206211.0922"
                             maxy="6301219.54"/>
                <BoundingBox CRS="EPSG:3034" minx="2105849.72282" miny="3395314.92817" maxx="3328080.90059"
                             maxy="4625140.43382"/>
                <BoundingBox CRS="EPSG:3035" minx="2492075.69943" miny="3696700.38021" maxx="3756694.61622"
                             maxy="4965316.69602"/>
                <BoundingBox CRS="EPSG:25833" minx="-515738.4438" miny="5013712.00055" maxx="836787.377131"
                             maxy="6365521.90145"/>
                <BoundingBox CRS="EPSG:25832" minx="-46133.17" miny="5048875.26858" maxx="1206211.10142"
                             maxy="6301219.54"/>
                <BoundingBox CRS="EPSG:2398" minx="5029021.86989" miny="3718843.83221" maxx="6331953.40417"
                             maxy="5022120.48358"/>
                <BoundingBox CRS="EPSG:32632" minx="-46133.1693302" miny="5048875.26313" maxx="1206211.09221"
                             maxy="6301219.54012"/>
                <BoundingBox CRS="EPSG:5678" minx="3718816.26917" miny="5028368.28237" maxx="5021927.1702"
                             maxy="6331143.97685"/>
                <BoundingBox CRS="EPSG:5679" minx="4483978.17542" miny="5015209.71611" maxx="5836880.71826"
                             maxy="6367405.54244"/>
                <BoundingBox CRS="EPSG:5676" minx="2137655.8341" miny="5034349.26604" maxx="3441878.73386"
                             maxy="6338240.22575"/>
                <BoundingBox CRS="EPSG:5677" minx="2953714.44984" miny="5050382.78746" maxx="4206408.14968"
                             maxy="6303085.58158"/>
                <BoundingBox CRS="EPSG:900913" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                             maxy="7729088.68047"/>
                <BoundingBox CRS="EPSG:5650" minx="32484261.5562" miny="5013712.00055" maxx="33836787.3771"
                             maxy="6365521.90145"/>
                <BoundingBox CRS="EPSG:2399" minx="5015856.7844" miny="4483976.0201" maxx="6368214.11758"
                             maxy="5837049.62448"/>
                <BoundingBox CRS="EPSG:2397" minx="5051043.35369" miny="2953771.7567" maxx="6303897.3097"
                             maxy="4206626.18653"/>
                <BoundingBox CRS="EPSG:32633" minx="-515738.443791" miny="5013712.00067" maxx="836787.377127"
                             maxy="6365521.90158"/>
                <BoundingBox CRS="EPSG:4326" minx="45.2375426953" miny="0.105946948013" maxx="56.8478734515"
                             maxy="20.4488892245"/>
                <BoundingBox CRS="EPSG:4258" minx="45.2375426953" miny="0.105946948013" maxx="56.8478734515"
                             maxy="20.4488892245"/>
                <BoundingBox CRS="EPSG:3068" minx="-779191.191332" miny="-865557.497855" maxx="550464.661196"
                             maxy="461310.425926"/>
                <BoundingBox CRS="EPSG:3045" minx="5013712.00055" miny="-515738.4438" maxx="6365521.90145"
                             maxy="836787.377131"/>
                <BoundingBox CRS="EPSG:3044" minx="5048875.26301" miny="-46133.1693348" maxx="6301219.54"
                             maxy="1206211.09222"/>
                <BoundingBox CRS="EPSG:102100" minx="11793.9603039" miny="5658995.56497" maxx="2276359.93575"
                             maxy="7729088.68047"/>
                <Style>
                    <Name>inspire_common:DEFAULT</Name>
                    <Title>default</Title>
                    <LegendURL width="400" height="800">
                        <Format>image/png</Format>
                        <OnlineResource xlink:type="simple"
                                        xlink:href="https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?styles=&amp;layer=webatlasde&amp;service=WMS&amp;format=image%2Fpng&amp;sld_version=1.1.0&amp;request=GetLegendGraphic&amp;version=1.1.1"/>
                    </LegendURL>
                </Style>
            </Layer>
        </Layer>
    </Capability>
</WMS_Capabilities>
WMS_XML,
                'wms',
            ],
            [
                <<<WMTS_XML
<?xml version="1.0"?>
<Capabilities xmlns="http://www.opengis.net/wmts/1.0" xmlns:ows="http://www.opengis.net/ows/1.1"
              xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
              xmlns:gml="http://www.opengis.net/gml"
              xsi:schemaLocation="http://www.opengis.net/wmts/1.0 http://schemas.opengis.net/wmts/1.0/wmtsGetCapabilities_response.xsd"
              version="1.0.0">
    <ows:ServiceIdentification>
        <ows:Title>LEP WMTS-Dienst</ows:Title>
        <ows:Abstract></ows:Abstract>
        <ows:ServiceType>OGC WMTS</ows:ServiceType>
        <ows:ServiceTypeVersion>1.0.0</ows:ServiceTypeVersion>
        <ows:Fees>none</ows:Fees>
        <ows:AccessConstraints>none</ows:AccessConstraints>
    </ows:ServiceIdentification>
    <ows:OperationsMetadata>
        <ows:Operation name="GetCapabilities">
            <ows:DCP>
                <ows:HTTP>
                    <ows:Get xlink:href="https://geodienstelandesplanungstage.bob-sh.de/mapproxy/lep/service?">
                        <ows:Constraint name="GetEncoding">
                            <ows:AllowedValues>
                                <ows:Value>KVP</ows:Value>
                            </ows:AllowedValues>
                        </ows:Constraint>
                    </ows:Get>
                </ows:HTTP>
            </ows:DCP>
        </ows:Operation>
        <ows:Operation name="GetTile">
            <ows:DCP>
                <ows:HTTP>
                    <ows:Get xlink:href="https://geodienstelandesplanungstage.bob-sh.de/mapproxy/lep/service?">
                        <ows:Constraint name="GetEncoding">
                            <ows:AllowedValues>
                                <ows:Value>KVP</ows:Value>
                            </ows:AllowedValues>
                        </ows:Constraint>
                    </ows:Get>
                </ows:HTTP>
            </ows:DCP>
        </ows:Operation>
        <ows:Operation name="GetFeatureInfo">
            <ows:DCP>
                <ows:HTTP>
                    <ows:Get xlink:href="https://geodienstelandesplanungstage.bob-sh.de/mapproxy/lep/service?">
                        <ows:Constraint name="GetEncoding">
                            <ows:AllowedValues>
                                <ows:Value>KVP</ows:Value>
                            </ows:AllowedValues>
                        </ows:Constraint>
                    </ows:Get>
                </ows:HTTP>
            </ows:DCP>
        </ows:Operation>
    </ows:OperationsMetadata>
    <Contents>
        <Layer>
            <ows:Title>lep_energie_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_energie_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_verkehr_1_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_verkehr_1_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_verkehr_2_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_verkehr_2_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_grenzen_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_grenzen_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_raumstruktur_1_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_raumstruktur_1_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_raumstruktur_2_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_raumstruktur_2_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_topographie_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_topographie_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_zo_c</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_zo_c</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>weisse_kachel</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>1.2756587791794693 50.161272479271794</ows:LowerCorner>
                <ows:UpperCorner>19.96457119634738 58.27612125993566</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>weisse_kachel</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/jpeg</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>topographie_weiss</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>1.2756587791794693 50.161272479271794</ows:LowerCorner>
                <ows:UpperCorner>19.96457119634738 58.27612125993566</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>topographie_weiss</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/jpeg</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_energie_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_energie_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_verkehr_1_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_verkehr_1_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_verkehr_2_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_verkehr_2_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_grenzen_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_grenzen_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_raumstruktur_1_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_raumstruktur_1_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_raumstruktur_2_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_raumstruktur_2_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_topographie_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_topographie_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>lep_zo_c_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>7.452192322311549 53.322715998024464</ows:LowerCorner>
                <ows:UpperCorner>11.733957088944097 55.13157024361736</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>lep_zo_c_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/png</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>weisse_kachel_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>1.2756587791794693 50.161272479271794</ows:LowerCorner>
                <ows:UpperCorner>19.96457119634738 58.27612125993566</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>weisse_kachel_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/jpeg</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <Layer>
            <ows:Title>topographie_weiss_2020</ows:Title>
            <ows:Abstract></ows:Abstract>
            <ows:WGS84BoundingBox>
                <ows:LowerCorner>1.2756587791794693 50.161272479271794</ows:LowerCorner>
                <ows:UpperCorner>19.96457119634738 58.27612125993566</ows:UpperCorner>
            </ows:WGS84BoundingBox>
            <ows:Identifier>topographie_weiss_2020</ows:Identifier>
            <Style>
                <ows:Identifier>default</ows:Identifier>
            </Style>
            <Format>image/jpeg</Format>
            <TileMatrixSetLink>
                <TileMatrixSet>DE_EPSG_25832_LEP</TileMatrixSet>
            </TileMatrixSetLink>
        </Layer>
        <TileMatrixSet>
            <ows:Identifier>DE_EPSG_25832_LEP</ows:Identifier>
            <ows:SupportedCRS>EPSG:25832</ows:SupportedCRS>
            <TileMatrix>
                <ows:Identifier>00</ows:Identifier>
                <ScaleDenominator>1417410.714285714</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>11</MatrixWidth>
                <MatrixHeight>9</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>01</ows:Identifier>
                <ScaleDenominator>1133928.5714285714</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>14</MatrixWidth>
                <MatrixHeight>11</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>02</ows:Identifier>
                <ScaleDenominator>566964.2857142857</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>28</MatrixWidth>
                <MatrixHeight>22</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>03</ows:Identifier>
                <ScaleDenominator>283482.14285714284</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>55</MatrixWidth>
                <MatrixHeight>43</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>04</ows:Identifier>
                <ScaleDenominator>141741.07142857142</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>109</MatrixWidth>
                <MatrixHeight>86</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>05</ows:Identifier>
                <ScaleDenominator>94494.04761892857</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>164</MatrixWidth>
                <MatrixHeight>128</MatrixHeight>
            </TileMatrix>
            <TileMatrix>
                <ows:Identifier>06</ows:Identifier>
                <ScaleDenominator>66145.83333321428</ScaleDenominator>
                <TopLeftCorner>44697.966114 6460648.876281</TopLeftCorner>
                <TileWidth>256</TileWidth>
                <TileHeight>256</TileHeight>
                <MatrixWidth>234</MatrixWidth>
                <MatrixHeight>183</MatrixHeight>
            </TileMatrix>
        </TileMatrixSet>
    </Contents>
</Capabilities>
WMTS_XML,
                'wmts',
            ],
        ];
    }

    /**
     * @dataProvider mapUrlsProvider
     */
    public function testEnsureUrlRequestsCapabilities($url, $expected): void
    {
        self::assertEquals($expected, $this->sut->ensureUrlRequestsCapabilities($url));
    }

    /**
     * @return array<int, mixed>
     */
    public function mapUrlsProvider(): array
    {
        return [
            [
                'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?Request=GetCapabilities',
                'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?Request=GetCapabilities',
            ],
            [
                '//foo.com?bar=baz&qoo=qux',
                '//foo.com?bar=baz&qoo=qux&Request=GetCapabilities',
            ],
            [
                'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?request=GetCapabilities',
                'https://sg.geodatenzentrum.de/wms_webatlasde__ce01ef82-8df3-d28f-edc0-bae62cfa13d6?request=GetCapabilities',
            ],
            [
                '//foo.com',
                '//foo.com?Request=GetCapabilities',
            ],
        ];
    }
}
