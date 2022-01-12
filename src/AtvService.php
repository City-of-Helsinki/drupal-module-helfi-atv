<?php

namespace Drupal\helfi_atv;

use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

/**
 * Communicate with ATV.
 */
class AtvService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Headers for requests.
   *
   * @var array
   */
  protected array $headers;

  /**
   * Base endpoint.
   *
   * @var string
   */
  private string $baseUrl;

  /**
   * Constructs an AtvService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;

    $this->headers = [
      'X-Api-Key' => getenv('ATV_API_KEY'),
    ];

    // @todo figure out tunnistamo based auth to atv
    $this->baseUrl = getenv('ATV_BASE_URL');

  }

  /**
   * Search documents with given arguments.
   *
   * @param array $searchParams
   *   Search params.
   *
   * @return array
   *   Data
   */
  public function searchDocuments(array $searchParams): array {

    $url = $this->buildUrl($searchParams);

    $responseData = $this->request(
      'GET',
      $url,
      [
        'headers' => $this->headers,
      ]
    );

    // If no data for some reason, don't fail, return empty array instead.
    if (!is_array($responseData)) {
      return [];
    }

    return $responseData['results'];

  }

  /**
   * Build request url with params.
   *
   * @param array $params
   *   Params for url.
   *
   * @return string
   *   Built url
   */
  private function buildUrl(array $params): string {
    $newUrl = $this->baseUrl;

    if (!empty($params)) {
      $paramCounter = 1;
      foreach ($params as $key => $value) {
        if ($paramCounter == 1) {
          $newUrl .= '?';
        }
        else {
          $newUrl .= '&';
        }
        $newUrl .= $key . '=' . $value;
        $paramCounter++;
      }
    }
    return $newUrl;
  }

  /**
   * List documents for this user.
   */
  public function listDocuments() {

  }

  /**
   * Fetch single document with id.
   *
   * @param string $id
   *   Document id.
   */
  public function getDocument(string $id): array {

    // $responseData = $this->request(
    // 'GET',
    // $this->baseUrl . $id,
    // [
    // 'headers' => $this->headers,
    // ]
    // );
    $dd = $this->demoData();
    $dJson = Json::decode($dd);
    return $dJson;

    // If no data for some reason, don't fail, return empty array instead.
    // if (!is_array($responseData)) {
    // return [];
    // }
    // return $responseData;.
  }

  /**
   * Parse malformed json.
   *
   * @param string $contentString
   *   JSON to be checked.
   *
   * @return mixed
   *   Decoded JSON array.
   */
  public function parseContent($contentString): mixed {
    $replaced = str_replace("'", "\"", $contentString);
    $replaced = str_replace("False", "false", $replaced);

    return Json::decode($replaced);
  }

  /**
   * Save new document.
   */
  public function postDocument() {

  }

  /**
   * Run PATCH query in ATV.
   *
   * @param string $id
   *   Document id to be patched.
   * @param array $document
   *   Document data to update.
   *
   * @return bool|null
   *   If PATCH succeeded?
   */
  public function patchDocument(string $id, array $document): ?bool {
    $patchUrl = $this->baseUrl . $id;

    $content = JSON::encode((object) $document);

    return $this->request(
      'PATCH',
      $patchUrl,
      [
        'headers' => $this->headers,
        'body' => $content,
      ]
    );
  }

  /**
   * Get document attachements.
   */
  public function getAttachments() {

  }

  /**
   * Get single attachment.
   *
   * @param string $id
   *   Id of attachment.
   */
  public function getAttachment(string $id) {

  }

  /**
   * Request wrapper for error handling.
   *
   * @param string $method
   *   Method for request.
   * @param string $url
   *   Endpoint.
   * @param array $options
   *   Options for request.
   *
   * @return bool|array
   *   Content or boolean if void.
   */
  private function request(string $method, string $url, array $options): bool|array {

    try {
      $resp = $this->httpClient->request(
        $method,
        $url,
        $options
      );
      if ($resp->getStatusCode() == 200) {
        if ($method == 'GET') {
          $bodyContents = $resp->getBody()->getContents();
          if (is_string($bodyContents)) {
            $bc = Json::decode($bodyContents);
            return $bc;
          }
          return $bodyContents;
        }
        else {
          return TRUE;
        }
      }
      return FALSE;
    }
    catch (ServerException | GuzzleException $e) {
      // @todo error handler for ATV request
      return FALSE;
    }
  }

  /**
   * Demo data when ATV is broken.
   *
   * @return string
   *   Demo data.
   */
  public function demoData(): string {
    $replaced = '{
	"id": "e5ed6430-4059-4284-859f-50137a1eee53",
	"created_at": "2021-12-21T13:35:10.411214+02:00",
	"updated_at": "2021-12-22T11:17:55.325143+02:00",
	"status": "handled",
	"type": "mysterious form",
	"transaction_id": "DRUPAL-00000057",
	"user_id": null,
	"business_id": "1234567-8",
	"tos_function_id": "f917d43aab76420bb2ec53f6684da7f7",
	"tos_record_id": "89837a682b5d410e861f8f3688154163",
	"metadata": {},
	"content": {
		"compensation": {
			"applicationInfoArray": [{
					"ID": "applicationType",
					"label": "Hakemustyyppi",
					"value": "ECONOMICGRANTAPPLICATION",
					"valueType": "string"
				},
				{
					"ID": "applicationTypeID",
					"label": "Hakemustyypin numero",
					"value": "29",
					"valueType": "int"
				},
				{
					"ID": "formTimeStamp",
					"label": "Hakemuksen/sanoman lähetyshetki",
					"value": "2022-01-04T08:42:46.000Z",
					"valueType": "datetime"
				},
				{
					"ID": "applicationNumber",
					"label": "Hakemusnumero",
					"value": "DRUPAL-00000007",
					"valueType": "string"
				},
				{
					"ID": "status",
					"label": "Tila",
					"value": "DRAFT",
					"valueType": "string"
				},
				{
					"ID": "actingYear",
					"label": "Hakemusvuosi",
					"value": "2022",
					"valueType": "int"
				}
			],
			"currentAddressInfoArray": [{
					"ID": "contactPerson",
					"label": "Yhteyshenkilö",
					"value": "jfghjw",
					"valueType": "string"
				},
				{
					"ID": "phoneNumber",
					"label": "Puhelinnumero",
					"value": "kjh",
					"valueType": "string"
				},
				{
					"ID": "street",
					"label": "Katuosoite",
					"value": "lkjh",
					"valueType": "string"
				},
				{
					"ID": "city",
					"label": "Postitoimipaikka",
					"value": "öljkh",
					"valueType": "string"
				},
				{
					"ID": "postCode",
					"label": "Postinumero",
					"value": "ökjh",
					"valueType": "string"
				},
				{
					"ID": "country",
					"label": "Maa",
					"value": "kjh",
					"valueType": "string"
				}
			],
			"applicantInfoArray": [{
					"ID": "applicantType",
					"label": "Hakijan tyyppi",
					"value": "2",
					"valueType": "string"
				},
				{
					"ID": "companyNumber",
					"label": "Rekisterinumero",
					"value": "4015026-5",
					"valueType": "string"
				},
				{
					"ID": "communityOfficialName",
					"label": "Yhteisön nimi",
					"value": "Oonan testiyhdistys syyskuu ry",
					"valueType": "string"
				},
				{
					"ID": "communityOfficialNameShort",
					"label": "Yhteisön lyhenne",
					"value": "jfhg",
					"valueType": "string"
				},
				{
					"ID": "registrationDate",
					"label": "Rekisteröimispäivä",
					"value": "17.09.2020",
					"valueType": "datetime"
				},
				{
					"ID": "foundingYear",
					"label": "Perustamisvuosi",
					"value": "2020",
					"valueType": "int"
				},
				{
					"ID": "home",
					"label": "Kotipaikka",
					"value": "HELSINKI",
					"valueType": "string"
				},
				{
					"ID": "homePage",
					"label": "www-sivut",
					"value": "www.yle.fi",
					"valueType": "string"
				},
				{
					"ID": "email",
					"label": "Sähköpostiosoite",
					"value": "email@domain.com",
					"valueType": "string"
				}
			],
			"applicantOfficialsArray": [
				[{
						"ID": "email",
						"label": "Sähköposti",
						"value": "a@d.com",
						"valueType": "string"
					},
					{
						"ID": "role",
						"label": "Rooli",
						"value": "3",
						"valueType": "string"
					},
					{
						"ID": "name",
						"label": "Nimi",
						"value": "asdf",
						"valueType": "string"
					},
					{
						"ID": "phone",
						"label": "Puhelinnumero",
						"value": "asdfasdf",
						"valueType": "string"
					}
				],

				[{
						"ID": "email",
						"label": "Sähköposti",
						"value": "asdf@d.com",
						"valueType": "string"
					},
					{
						"ID": "role",
						"label": "Rooli",
						"value": "2",
						"valueType": "string"
					},
					{
						"ID": "name",
						"label": "Nimi",
						"value": "poiuflaskdjf öaslkdfh ",
						"valueType": "string"
					},
					{
						"ID": "phone",
						"label": "Puhelinnumero",
						"value": "354654324354",
						"valueType": "string"
					}
				]
			],
			"bankAccountArray": [{
				"ID": "accountNumber",
				"label": "Tilinumero",
				"value": "3245-2345",
				"valueType": "string"
			}],
			"compensationInfo": {
				"generalInfoArray": [{
						"ID": "totalAmount",
						"label": "Haettavat avustukset yhteensä",
						"value": "0111",
						"valueType": "float"
					},
					{
						"ID": "noCompensationPreviousYear",
						"label": "Olen saanut Helsingin kaupungilta avustusta samaan käyttötarkoitukseen edellisenä vuonna",
						"value": "true",
						"valueType": "string"
					},
					{
						"ID": "purpose",
						"label": "Haetun avustuksen käyttötarkoitus",
						"value": "asdasdfasdf",
						"valueType": "string"
					},
					{
						"ID": "explanation",
						"label": "Selvitys edellisen avustuksen käytöstä",
						"value": "asdfasdf asdfasdf asdf",
						"valueType": "string"
					}
				],
				"compensationArray": [
					[{
							"ID": "subventionType",
							"label": "Avustuslaji",
							"value": "6",
							"valueType": "string"
						},
						{
							"ID": "amount",
							"label": "Euroa",
							"value": "111",
							"valueType": "float"
						}
					]
				]
			},
			"otherCompensationsInfo": {
				"otherCompensationsArray": [
					[{
							"ID": "issuer",
							"label": "Myöntäjä",
							"value": "2",
							"valueType": "string"
						},
						{
							"ID": "issuerName",
							"label": "Myöntäjän nimi",
							"value": "asdf asdfasdf",
							"valueType": "string"
						},
						{
							"ID": "year",
							"label": "Vuosi",
							"value": "2222",
							"valueType": "string"
						},
						{
							"ID": "amount",
							"label": "Euroa",
							"value": "2222",
							"valueType": "float"
						},
						{
							"ID": "purpose",
							"label": "Tarkoitus",
							"value": "dsfasdfasdfasdf",
							"valueType": "string"
						}
					],
					[{
							"ID": "issuer",
							"label": "Myöntäjä",
							"value": "4",
							"valueType": "string"
						},
						{
							"ID": "issuerName",
							"label": "Myöntäjän nimi",
							"value": "asdfasdfasdf",
							"valueType": "string"
						},
						{
							"ID": "year",
							"label": "Vuosi",
							"value": "3333",
							"valueType": "string"
						},
						{
							"ID": "amount",
							"label": "Euroa",
							"value": "3333",
							"valueType": "float"
						},
						{
							"ID": "purpose",
							"label": "Tarkoitus",
							"value": "asdfasdf",
							"valueType": "string"
						}
					]
				],
				"otherCompensationsTotal": "022223333"
			},
			"benefitsInfoArray": [{
					"ID": "premises",
					"label": "Tilat, jotka kaupunki on antanut korvauksetta tai vuokrannut hakijan käyttöön (osoite, pinta-ala ja tiloista maksettava vuokra €/kk",
					"value": " asdfasdf adfasfasdf",
					"valueType": "string"
				},
				{
					"ID": "loans",
					"label": "Kaupungilta saadut lainat ja/tai takaukset",
					"value": "sdafads asdfasdfasdf",
					"valueType": "string"
				}
			],
			"activitiesInfoArray": [{
					"ID": "businessPurpose",
					"label": "Toiminnan tarkoitus",
					"value": "Meidän toimintamme tarkoituksena on että ...",
					"valueType": "string"
				},
				{
					"ID": "communityPracticesBusiness",
					"label": "Yhteisö harjoittaa liiketoimintaa",
					"value": "false",
					"valueType": "bool"
				},
				{
					"ID": "membersApplicantPersonGlobal",
					"label": "Hakijayhteisö, henkilöjäseniä",
					"value": "333",
					"valueType": "int"
				},
				{
					"ID": "membersApplicantPersonLocal",
					"label": "Hakijayhteisö, helsinkiläisiä henkilöjäseniä",
					"value": "3333",
					"valueType": "int"
				},
				{
					"ID": "membersApplicantCommunityGlobal",
					"label": "Hakijayhteisö, yhteisöjäseniä",
					"value": "333",
					"valueType": "int"
				},
				{
					"ID": "membersApplicantCommunityLocal",
					"label": "Hakijayhteisö, helsinkiläisiä yhteisöjäseniä",
					"value": "33",
					"valueType": "int"
				},
				{
					"ID": "feePerson",
					"label": "Jäsenmaksun suuruus, Henkiöjäsen euroa",
					"value": "333",
					"valueType": "float"
				},
				{
					"ID": "feeCommunity",
					"label": "Jäsenmaksun suuruus, Yhteisöjäsen euroa",
					"value": "333",
					"valueType": "float"
				}
			],
			"additionalInformation": "Pellentesque sed tellus quis sapien suscipit rhoncus. Duis vitae risus bibendum, vehicula massa ac, porttitor lorem.",
			"senderInfoArray": [{
					"ID": "firstname",
					"label": "Etunimi",
					"value": "Nordea",
					"valueType": "string"
				},
				{
					"ID": "lastname",
					"label": "Sukunimi",
					"value": "Demo",
					"valueType": "string"
				},
				{
					"ID": "personID",
					"label": "Henkilötunnus",
					"value": "210281-9988",
					"valueType": "string"
				},
				{
					"ID": "userID",
					"label": "Käyttäjätunnus",
					"value": "UHJvZmlsZU5vZGU6NzdhMjdhZmItMzQyNi00YTMyLTk0YjEtNzY5MWNiNjAxYmU5",
					"valueType": "string"
				},
				{
					"ID": "email",
					"label": "Sähköposti",
					"value": "aki.koskinen@hel.fi",
					"valueType": "string"
				}
			]
		},
		"attachmentsInfo": {
			"attachmentsArray": [
				[{
						"ID": "description",
						"value": "Vahvistettu tilinpäätös (edelliseltä päättyneeltä tilikaudelta)",
						"valueType": "string"
					},
					{
						"ID": "isDeliveredLater",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
						"ID": "description",
						"value": "Vahvistettu toimintakertomus (edelliseltä päättyneeltä tilikaudelta)",
						"valueType": "string"
					},
					{
						"ID": "isDeliveredLater",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
						"ID": "description",
						"value": "Vahvistettu tilin- tai toiminnantarkastuskertomus (edelliseltä päättyneeltä tilikaudelta)",
						"valueType": "string"
					},
					{
						"ID": "fileName",
						"value": "sample.pdf",
						"valueType": "string"
					},
					{
						"ID": "isNewAttachment",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "fileType",
						"value": 0,
						"valueType": "int"
					},
					{
						"ID": "isDeliveredLater",
						"value": "false",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
						"ID": "description",
						"value": "Vuosikokouksen pöytäkirja, jossa on vahvistettu edellisen päättyneen tilikauden tilinpäätös",
						"valueType": "string"
					},
					{
						"ID": "isDeliveredLater",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
						"ID": "description",
						"value": "Toimintasuunnitelma (sille vuodelle jolle haet avustusta)",
						"valueType": "string"
					},
					{
						"ID": "isDeliveredLater",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
						"ID": "description",
						"value": "Talousarvio (sille vuodelle jolle haet avustusta)",
						"valueType": "string"
					},
					{
						"ID": "isDeliveredLater",
						"value": "true",
						"valueType": "bool"
					},
					{
						"ID": "isIncludedInOtherFile",
						"value": "false",
						"valueType": "bool"
					}
				],
				[{
					"ID": "description",
					"value": "Muu liite",
					"valueType": "string"
				}]
			]
		},
		"formUpdate": false
	},
	"draft": false,
	"locked_after": null,
	"attachments": []
}';
    return $replaced;
  }

}
