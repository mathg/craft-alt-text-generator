<?php

namespace dispositiontools\craftalttextgenerator\controllers;

use Craft;
use craft\web\Controller;
use dispositiontools\craftalttextgenerator\AltTextGenerator;
use dispositiontools\craftalttextgenerator\jobs\RequestAltText as RequestAltTextJob;

use yii\web\Response;

/**
 * Cp controller
 */
class CpController extends Controller {
	public $defaultAction = 'index';
	protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;


	/**
	 * alt-text-generator/cp/dashboard action
	 */
	public function actionDashboard(): Response {
		// ...
		$this->requirePermission('altTextGeneratorViewDashboard');

		$request = Craft::$app->getRequest();



		$settings = AltTextGenerator::getInstance()->getSettings();
		if (!$settings->getApiKey(true)) {
			return $this->renderTemplate('alt-text-generator/_cp/setup', ['title' => 'Alt Text Generator']);
		}

		$apiCreditsCount = AltTextGenerator::getInstance()->altTextAiApi->getNumberOfAltTextApiCredits();

		// We need these three request parameters for the view. ("value" optional)
		$templateParams = [
			'title' => 'Alt Text Generator',
			'settings' => $settings,
			'credits' => $apiCreditsCount,
			'apiCalls' => AltTextGenerator::getInstance()->altTextAiApi->getApiCalls([
				'where' =>
				[
					'altTextSyncStatus' => ['review'],
				],
			]),
		];
		return $this->renderTemplate('alt-text-generator/_cp/dashboard', $templateParams);
	}



	/**
	 * alt-text-generator/cp/history action
	 */
	public function actionHistory(): Response {
		// ...
		$this->requirePermission('altTextGeneratorViewHistory');
		$request = Craft::$app->getRequest();

		$settings = AltTextGenerator::getInstance()->getSettings();


		// We need these three request parameters for the view. ("value" optional)
		$templateParams = [
			'title' => 'Alt Text Generator',
			'settings' => $settings,
			'apiCalls' => AltTextGenerator::getInstance()->altTextAiApi->getApiCalls([]),
		];
		return $this->renderTemplate('alt-text-generator/_cp/history', $templateParams);
	}


	/**
	 * alt-text-generator/cp/errors action
	 */
	public function actionErrors(): Response {
		// ...
		$this->requirePermission('altTextGeneratorViewHistory');
		$request = Craft::$app->getRequest();

		$settings = AltTextGenerator::getInstance()->getSettings();


		// We need these three request parameters for the view. ("value" optional)
		$templateParams = [
			'title' => 'Alt Text Generator',
			'settings' => $settings,
			'apiCalls' => AltTextGenerator::getInstance()->altTextAiApi->getApiCalls([
				'where' =>
				[
					'altTextSyncStatus' => ['errors'],
				],
			]),
		];
		return $this->renderTemplate('alt-text-generator/_cp/errors', $templateParams);
	}


	/**
	 * alt-text-generator/cp/queue-images action
	 */
	public function actionQueueImages(): Response {
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();
		$generateForNoAltText = $request->post('generateForNoAltText');
		$generateForAltText = $request->post('generateForAltText');
		$overwrite = $request->post('overwrite', false);



		$queueAllImagesReport = AltTextGenerator::getInstance()->altTextAiApi->queueAllImages($generateForNoAltText, $generateForAltText, $overwrite);

		return $this->renderTemplate('alt-text-generator/_cp/queue_all_report', [
			"title" => "Queue Assets report",
			"queueAllImagesReport" => $queueAllImagesReport
		]);
		//return $this->asSuccess(json_encode($queueAllImagesReport));
	}




	/**
	 * alt-text-generator/cp/queue-all-images-for-resync action
	 */
	public function actionQueueAllImagesForResync(): Response {
		$imagesQueuedForResync = AltTextGenerator::getInstance()->altTextAiApi->queueAllImagesForResync();

		Craft::$app->getSession()->setSuccess($imagesQueuedForResync['imagesQueue'] . ' images queue for resync');

		return $this->redirectToPostedUrl();
	}


	/**
	 * alt-text-generator/cp/refresh-api-token-count action
	 */
	public function actionRefreshApiTokenCount(): Response {
		AltTextGenerator::getInstance()->altTextAiApi->refreshNumberOfAltTextApiCredits();
		return $this->redirectToPostedUrl();
	}

	/**
	 * alt-text-generator/cp/update-api-calls action
	 */
	public function actionUpdateApiCalls(): Response {
		$request = Craft::$app->getRequest();

		$assets = $request->post('assets');
		$altTextUpdates = $request->post('altTextUpdates');

		AltTextGenerator::getInstance()->altTextAiApi->updateApiCalls($assets, $altTextUpdates);

		// Go through each type of edit and get it done.
		return $this->redirectToPostedUrl();
	}

	/**
	 * alt-text-generator/cp/queue-single-alt-text action
	 */
	public function actionQueueSingleAltText(): Response {
		$this->requirePostRequest();
		$this->requireAcceptsJson();

		$assetId = Craft::$app->getRequest()->getRequiredParam('assetId');

		$siteId = Craft::$app->request->getQueryParam('site');
		$currentSite = $siteId ? Craft::$app->getSites()->getSiteByHandle($siteId) : Craft::$app->getSites()->getPrimarySite();

		Craft::$app->getQueue()->push(new RequestAltTextJob([
			'assetId' => $assetId,
			'requestUserId' => Craft::$app->getUser()->getId(),
			'actionType' => 'Action',
			"overwrite" => true,
			"siteId" => $currentSite->id
		]));

		return $this->asJson(['success' => true]);
	}
}
