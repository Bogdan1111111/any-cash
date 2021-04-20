<?php

namespace App\Controller;

use App\Entity\Checkout;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Dotenv\Dotenv;

class MainController extends AbstractController
{
    private $client;

    // TODO: to SDK
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    /**
     * @Route("/", name="main")
     */
    public function index(Request $request): Response
    {
		$orderForm = new Checkout();

		$form = $this->createFormBuilder($orderForm)
			->add('amount', TextType::class, ['label' => 'Enter Amount'])
			->add('submit', SubmitType::class, ['label' => 'Create Order'])
			->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()){
            $form->getData();
            $amount = $orderForm->getAmount();
            $result = $this->createOrder($amount);

            return $this->render('main/checkout.html.twig', [
                'response' => isset($result->error) ? $result->error->message : $result->id
            ]);
        }

        return $this->render('main/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // TODO: to SDK
    private function createOrder($amount)
    {
	    $utc_now = strval(((int)round(microtime(true) * 1000)));
	    $params = [
	    	'amount' => strval($amount),
		    'externalid' => strval(time()),
            'out_curr' => 'BTC',
            'payway' => 'anycash'
        ];
	    $data = [
            'params' => $params,
            'method'  => 'payout.create',
            "jsonrpc" => "2.0",
            "id" => strval(time())
        ];
	    $jsonData = json_encode($data);

	    $response = $this->client->request('POST', 'https://api.any.money/', [
		    'headers' => [
			    'Content-Type: application/json',
			    'Content-Length: ' . strlen($jsonData),
			    'x-merchant: ' . $_ENV['MERCHANT'],
			    'x-signature: ' . $this->signData($_ENV['API_KEY'], $params, $utc_now),
			    'x-utc-now-ms: ' . $utc_now
		    ],
	        'body' => $jsonData
	    ]);

		return json_decode($response->getContent());
    }

    // TODO: to SDK
    private function signData($key, $data, $utc_now) : string {
	    ksort($data);
	    $s = '';
	    foreach($data as $k=>$value) {
		    if (in_array(gettype($value), array('array', 'object', 'NULL')) ){
			    continue;
		    }
		    if(is_bool($value)){
			    $s .= $value ? "true" : "false";
		    } else {
			    $s .= $value;
		    }
	    }
	    $s .= $utc_now;

	    return hash_hmac('sha512', strtolower($s), $key);
    }
}
