<?php

namespace Drupal\Tests\graphql\Kernel\Framework;

use Drupal\Core\File\FileSystem;
use Drupal\graphql\GraphQL\Utility\FileUpload;
use Drupal\Tests\graphql\Kernel\GraphQLTestBase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests file uploads that should be mapped to a field in a resolver.
 *
 * @group graphql
 */
class UploadFileServiceTest extends GraphQLTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['file'];

  /**
   * The FileUpload object we want to test, gets prepared in setUp().
   *
   * @var \Drupal\graphql\GraphQL\Utility\FileUpload
   */
  protected $uploadService;

  /**
   * Path to temporary test file.
   *
   * @var string
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('file');

    $file_system = \Drupal::service('file_system');

    // Pass all file system calls through except moveUploadedFile(). We don't
    // have a real uploaded file, so it would fail PHP's move_uploaded_file()
    // checks.
    $mock_file_system = new class($file_system) extends FileSystem {

      public function __construct(FileSystem $file_system) {
        $this->fileSystem = $file_system;
      }

      public function prepareDirectory(&$directory, $options = self::MODIFY_PERMISSIONS) {
        return $this->fileSystem->prepareDirectory($directory, $options);
      }

      public function moveUploadedFile($filename, $uri) {
        // We can use the normal move() functionality instead during testing.
        return $this->fileSystem->move($filename, $uri);
      }

      public function chmod($uri, $mode = NULL) {
        return $this->fileSystem->chmod($uri, $mode);
      }
    };

    $this->uploadService = new FileUpload(
      \Drupal::service('entity_type.manager'),
      \Drupal::service('current_user'),
      \Drupal::service('file.mime_type.guesser'),
      $mock_file_system,
      \Drupal::service('logger.channel.graphql')
    );

    // Create dummy file, since symfony will test if it exists.
    $this->file = $file_system->getTempDirectory() . '/graphql_upload_test.txt';
    touch($this->file);
  }

  /**
   * Ensure that a correct file upload works.
   */
  public function testSuccess() {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = new UploadedFile($this->file, 'test.txt', 'text/plain', UPLOAD_ERR_OK, TRUE);

    $file_upload_response = $this->uploadService->createTemporaryFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $file_entity = $file_upload_response->getFileEntity();
    $file_entity->save();

    $this->assertSame('public://test/test.txt', $file_entity->getFileUri());
    $this->assertFileExists($file_entity->getFileUri());
  }

  /**
   * Tests that a too large file returns a violation.
   */
  public function testFileTooLarge() {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = new UploadedFile($this->file, 'test.txt', 'text/plain', UPLOAD_ERR_INI_SIZE, TRUE);

    $file_upload_response = $this->uploadService->createTemporaryFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $violations = $file_upload_response->getViolations();

    $this->assertStringMatchesFormat(
      'The file test.txt could not be saved because it exceeds %d %s, the maximum allowed size for uploads.',
      $violations[0]
    );
  }

  /**
   * Tests that a partial file returns a violation.
   */
  public function testPartialFile() {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = new UploadedFile($this->file, 'test.txt', 'text/plain', UPLOAD_ERR_PARTIAL, TRUE);

    $file_upload_response = $this->uploadService->createTemporaryFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $violations = $file_upload_response->getViolations();

    $this->assertStringMatchesFormat(
      'The file "test.txt" could not be saved because the upload did not complete.',
      $violations[0]
    );
  }

  /**
   * Tests that missing settings keys throw an exception.
   */
  public function testMissingSettings() {
    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = new UploadedFile($this->file, 'test.txt', 'text/plain', UPLOAD_ERR_OK, TRUE);

    $this->expectException(\RuntimeException::class);
    $this->uploadService->createTemporaryFileUpload($uploadFile, []);
  }

  /**
   * Tests that the file must not be larger than the file size limit.
   */
  public function testSizeValidation() {
    // Create a file with 4 bytes.
    file_put_contents($this->file, 'test');

    // Create a Symfony dummy uploaded file in test mode.
    $uploadFile = new UploadedFile($this->file, 'test.txt', 'text/plain', UPLOAD_ERR_OK, TRUE);

    $file_upload_response = $this->uploadService->createTemporaryFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
      // Only allow 1 byte.
      'max_filesize' => 1,
    ]);
    $violations = $file_upload_response->getViolations();

    // @todo Do we want HTML tags in our violations or not?
    $this->assertStringMatchesFormat(
      'The file is <em class="placeholder">4 bytes</em> exceeding the maximum file size of <em class="placeholder">1 byte</em>.',
      $violations[0]
    );
  }

  /**
   * Tests that the uploaded file extension is allowed
   */
  public function testExtensionValidation() {
    // Evil php file extension!
    $uploadFile = new UploadedFile($this->file, 'test.php', 'text/plain', UPLOAD_ERR_OK, TRUE);

    $file_upload_response = $this->uploadService->createTemporaryFileUpload($uploadFile, [
      'uri_scheme' => 'public',
      'file_directory' => 'test',
    ]);
    $violations = $file_upload_response->getViolations();

    // @todo Do we want HTML tags in our violations or not?
    $this->assertStringMatchesFormat(
      'Only files with the following extensions are allowed: <em class="placeholder">txt</em>.',
      $violations[0]
    );
  }

}