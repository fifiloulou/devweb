<?php

namespace App\DataFixtures;

use App\Entity\Picture;
use App\Service\FileUploader;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

class PictureFixtures extends Fixture
{
    /**
     * @var array<string>
     */
    private static array $pictures = [
        'pizza.jpg',
        'pizza2.jpg',
        'spaghetti.jpg',
        'spaghetti2.jpg',
        'vino.jpg',
        'vino2.jpg',
    ];

    private string $filesToUploadDirectory;

    private string $filesToUploadDirectoryCopy;

    private FileUploader $fileUploader;

    private FileSystem $fileSystem;

    private ObjectManager $manager;

    private string $uploadsDirectory;

    public function __construct(FileUploader $fileUploader, FileSystem $fileSystem, KernelInterface $kernel, string $uploadsDirectory)
    {
        $this->fileUploader = $fileUploader;
        $this->fileSystem = $fileSystem;
        $this->filesToUploadDirectory = "{$kernel->getProjectDir()}/public/to-upload/";
        $this->filesToUploadDirectoryCopy = "{$kernel->getProjectDir()}/public/to-upload-copy/";
        $this->uploadsDirectory = $uploadsDirectory;
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $this->copyToUploadDirectory();

        $this->removeExistingUploadDirectoryAndRecreate();

        $this->generateArticlePicture();

        $this->renameToUploadDirectoryCopy();

        $manager->flush();
    }

    private function copyToUploadDirectory(): void
    {
        $this->fileSystem->mkdir($this->filesToUploadDirectoryCopy);

        $this->fileSystem->mirror($this->filesToUploadDirectory, $this->filesToUploadDirectoryCopy);
    }

    private function removeExistingUploadDirectoryAndRecreate(): void
    {
        if ($this->fileSystem->exists($this->uploadsDirectory)) {
            $this->fileSystem->remove($this->uploadsDirectory);
    
            $this->fileSystem->mkdir($this->uploadsDirectory);
        }

    }

    private function generateArticlePicture(): void
    {
        foreach (self::$pictures as $key => $pictureFile) {
            $picture = new Picture();

            [
                'fileName' => $pictureName,
                'filePath' => $picturePath  
            ] = $this->fileUploader->upload(new UploadedFile($this->filesToUploadDirectory .$pictureFile, $pictureFile, null, null, true));

            $picture->setPictureName($pictureName)
                    ->setPicturePath($picturePath);

            $this->addReference("picture{$key}", $picture);

            $this->manager->persist($picture);

            if ($key === array_key_last(self::$pictures)) {
                $this->fileSystem->remove($this->filesToUploadDirectory);
            }
        }
    }

    private function renameToUploadDirectoryCopy(): void {

        $this->fileSystem->rename($this->filesToUploadDirectoryCopy, $this->filesToUploadDirectory);
    }
}
