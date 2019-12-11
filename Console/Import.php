<?php
namespace Mamis\BlogImport\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\Filesystem\DirectoryList;

class Import extends Command
{
    const FILENAME = 'filename';
    const STOREID = 'storeid';

    protected $csv;
    protected $directoryList;
    protected $postFactory;
    protected $postModel;

    public function __construct(
        \Magento\Framework\File\Csv $csv,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magefan\Blog\Model\PostFactory $postFactory,
        \Magefan\Blog\Model\Post $postModel
    )
    {
        parent::__construct();
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->postFactory = $postFactory;
        $this->postModel = $postModel;
    }

    protected function configure()
	{

		$options = [
			new InputOption(
				self::FILENAME,
				null,
				InputOption::VALUE_REQUIRED,
				'CSV file name to be imported. Note: This has to be inside media/import/blogs/ directory.'
            ),
            new InputOption(
				self::STOREID,
				null,
				InputOption::VALUE_REQUIRED,
				'Store Id. Note: Store ID where blogs will be imported.'
            ),
		];

		$this->setName('mamis:importblogs')
			->setDescription('Import blogs')
			->setDefinition($options);

		parent::configure();
	}


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!empty($input->getOption(self::FILENAME)) && !empty($input->getOption(self::STOREID))) {
            $mediaPath = $this->directoryList->getPath('media');
            $filePath = $mediaPath . '/import/blogs/' . $input->getOption(self::FILENAME) . '.csv';

            try {
                $blogPosts = $this->csv->getData($filePath);
                $postFactory = $this->postFactory->create();

                foreach ($blogPosts as $rowIndex => $post) {
                    if ($rowIndex >0) {
                        //Get Object Manager Instance
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                        // Create new post model
                        $postModel = $objectManager->create(\Magefan\Blog\Model\Post::class);
                        // Store
                        $postModel->setStoreIds([$input->getOption(self::STOREID)]);
                        // Categories @TODO: dynamic
                        $postModel->setCategories([3]);
                        // Title
                        $postModel->setTitle($post[1]);
                        // Content
                        $postModel->setContent($post[2]);
                        // Short Content
                        $postModel->setShortContent($post[14]);
                        // Image
                        $postModel->setFeaturedImg($post[3]);
                        // Status
                        $postModel->setIsActive($post[4]);
                        // Created At
                        $postModel->setCreationTime($post[5]);
                        $postModel->setPublishTime($post[5]);
                        // Updated At
                        $postModel->setUpdateTime($post[6]);
                        // Identifier
                        $postModel->setIdentifier($post[7]);
                        // Meta Keywords
                        $postModel->setMetaKeywords($post[10]);
                        // Meta Description
                        $postModel->setMetaDescription($post[11]);
                        // Set Tags
                        $tagIds = $this->getPostTagIds($post[13]);
                        $postModel->setTags($tagIds);

                        $postModel->save();
                    }
                }
            }
            catch (Exception $e) {
                $errorMessage = $e->getMessage();
                $output->writeln("<error>$errorMessage</error>");
            }

        }
        else {
            $output->writeln("<error>Missing Required Inputs.</error>");
        }

        return $this;
    }

    protected function getPostTagIds($tagInput)
    {
        $tagInput = explode(',', $tagInput);

        //Get Object Manager Instance
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $tagsCollection = $objectManager->create(\Magefan\Blog\Model\ResourceModel\Tag\Collection::class);
        $allTags = [];
        foreach ($tagsCollection as $item) {
            $allTags[strtolower($item->getTitle())] = $item->getId();
        }

        $tags = [];
        foreach ($tagInput as $tagTitle) {
            if (empty($allTags[strtolower($tagTitle)])) {
                $tagModel = $objectManager->create(\Magefan\Blog\Model\Tag::class);
                $tagModel->setData('title', $tagTitle);
                $tagModel->setData('is_active', 1);
                $tagModel->save();

                $tags[] = $tagModel->getId();
            } else {
                $tags[] = $allTags[$tagTitle];
            }
        }

        return $tags;
    }
}
