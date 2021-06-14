<?php

declare(strict_types=1);

/*
 * This file is part of the "Gone" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 */

namespace Leuchtfeuer\Gone\Controller;

use Leuchtfeuer\Gone\Domain\Repository\HistoryRepository;
use Leuchtfeuer\Gone\Utility\ConfigurationUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

class BackendController extends ActionController
{
    private const FLASH_MESSAGE_QUEUE_IDENTIFIER = 'leuchtfeuer.gone.flashMessages';

    protected $defaultViewObjectName = BackendTemplateView::class;

    protected $historyRepository;

    protected $configurationUtility;

    protected $messageQueue;

    public function __construct(
        HistoryRepository $historyRepository,
        ConfigurationUtility $configurationUtility,
        FlashMessageService $flashMessageService
    ) {
        if (version_compare(TYPO3_version, '10.0.0', '<')) {
            parent::__construct();
        }

        $this->historyRepository = $historyRepository;
        $this->configurationUtility = $configurationUtility;
        $this->messageQueue = $flashMessageService->getMessageQueueByIdentifier(self::FLASH_MESSAGE_QUEUE_IDENTIFIER);
    }

    public function listAction(): void
    {
        $this->view->assignMultiple([
            'entries' => $this->historyRepository->findAll(),
            'configuration' => $this->configurationUtility->getConfigurationForModule(),
        ]);
    }

    public function deleteAction(int $entry): void
    {
        $this->historyRepository->delete($entry);
        $this->createFlashMessage('Entry removed', 'Deleted', FlashMessage::OK);
        $this->redirect('list');
    }

    public function createFlashMessage(string $text, string $title = '', int $severity = FlashMessage::OK, bool $storeInSession = true): void
    {
        $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $text, $title, $severity, $storeInSession);
        $this->messageQueue->addMessage($flashMessage);
    }

    protected function initializeView(ViewInterface $view): void
    {
        parent::initializeView($view);

        // View could be instance of NotFoundView
        if ($view instanceof BackendTemplateView) {
            // Ajax Data Handler for delete
            $view->getModuleTemplate()
                 ->getPageRenderer()
                 ->loadRequireJsModule('TYPO3/CMS/Backend/AjaxDataHandler');
        }
    }
}
