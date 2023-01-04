<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Hashcrypt\Contact\Controller\Index;


use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\App\ObjectManager;

use Magento\Contact\Model\ConfigInterface;
use Magento\Contact\Model\MailInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Psr\Log\LoggerInterface;


class Index extends \Magento\Contact\Controller\Index\Post
{
    private $dataPersistor;
    /**
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */

    protected $context;
    private $fileUploaderFactory;
    private $fileSystem;


    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
     protected $storeManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */


    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */

     public function __construct(
        \Magento\Framework\App\Action\Context $context,
        ConfigInterface $contactsConfig,
        MailInterface $mail,
        DataPersistorInterface $dataPersistor,
        LoggerInterface $logger = null,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Filesystem $fileSystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory
    ) {
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem          = $fileSystem;

        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->_transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;

        parent::__construct($context,$contactsConfig,$mail,$dataPersistor,$logger);

    }

    /**
     * Post user question
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        $post = $this->getRequest()->getPostValue();
        if (!$post) {
            $this->_redirect('contact');
            return;
        }



        $this->inlineTranslation->suspend();
        try {
            $postObject = new \Magento\Framework\DataObject();
            $postObject->setData($post);

            $error = false;

            if (!\Zend_Validate::is(trim($post['name']), 'NotEmpty')) {
                $error = true;
            }
            if (!\Zend_Validate::is(trim($post['comment']), 'NotEmpty')) {
                $error = true;
            }
            if (!\Zend_Validate::is(trim($post['email']), 'EmailAddress')) {
                $error = true;
            }
            if (\Zend_Validate::is(trim($post['hideit']), 'NotEmpty')) {
                $error = true;
            }




            $filesData = $this->getRequest()->getFiles('document');

       
            if ($filesData['name']) {
                try {


                // init uploader model.
                    $uploader = $this->fileUploaderFactory->create(['fileId' => 'document']);
                    $uploader->setAllowRenameFiles(true);
                    $uploader->setFilesDispersion(true);
                    $uploader->setAllowCreateFolders(true);
                    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'pdf', 'docx']);
                    $path = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('contact-doc');
                    $result = $uploader->save($path);





                    $upload_document = 'contact-doc'.$uploader->getUploadedFilename();
                    $filePath = $result['path'].$result['file'];
                    $fileName = $result['name'];
                }catch (\Exception $e) {
                    $this->inlineTranslation->resume();
                    $this->messageManager->addError(
                        __('File format not supported.')
                    );
                    $this->getDataPersistor()->set('contact', $post);
                    $this->_redirect('contact');
                    return;
                }
                
            } else {
                $upload_document = '';
                $filePath = '';
                $fileName = '';
            }




            if ($error) {
                throw new \Exception();
            }

            $this->inlineTranslation->suspend();



            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

            $ti = $this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE, $storeScope);
            $topts = ['area' => 'frontend','store' => 1];

            $from = $this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER, $storeScope);
            $to = $this->scopeConfig->getValue(self::XML_PATH_EMAIL_RECIPIENT, $storeScope);
            $tv = ['data' => $postObject];

            if ($filesData['name']) {
            
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
             

                $transport = $this->_transportBuilder
                    ->setTemplateIdentifier($ti)
                    ->setTemplateOptions($topts)
                    ->setTemplateVars($tv)
                    ->setFrom($from)
                    ->addTo($to)
                    ->addAttachment(file_get_contents($filePath), $fileName, $ext ) 
                    ->setReplyTo($post['email'])
                    ->getTransport();

            }else{

                  $transport = $this->_transportBuilder
                    ->setTemplateIdentifier($ti)
                    ->setTemplateOptions($topts)
                    ->setTemplateVars($tv)
                    ->setFrom($from)
                    ->addTo($to)                
                    ->setReplyTo($post['email'])
                    ->getTransport();
            }


            $transport->sendMessage();

            $this->inlineTranslation->resume();
            $this->messageManager->addSuccess(
                __('Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.')
            );
            $this->getDataPersistor()->clear('contact_us');



            $pos = strpos($_SERVER['HTTP_REFERER'], 'orcamento');

            if ($pos === false) {
                $this->_redirect('contact');
            } else {
                $this->_redirect('orcamento');
            }



            return;
        } catch (\Exception $e) {
            $this->inlineTranslation->resume();
            $this->messageManager->addError(
               $e->getMessage()
            );
            $this->getDataPersistor()->set('contact_us', $post);
            $this->_redirect('contact');
            return;
        }
    }
    /**
     * Get Data Persistor
     *
     * @return DataPersistorInterface
     */
    private function getDataPersistor()
    {
        if ($this->dataPersistor === null) {
            $this->dataPersistor = ObjectManager::getInstance()
                ->get(DataPersistorInterface::class);
        }

        return $this->dataPersistor;
    }

   

}