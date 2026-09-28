<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\ContactMessages;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentValidator;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

final class ContactAttachmentValidatorTest extends WP_UnitTestCase
{
	use ContactAttachmentFixtures;
	use SandboxesUploads;

	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = $this->sandbox_uploads() . '/in';
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		$this->remove_sandbox();
	}

	/** @return array<string, array{0:string,1:string,2:string}> kind, claimed name, expected mime */
	public static function accepted(): array
	{
		return array(
			'pdf'  => array( 'pdf', 'offer.pdf', 'application/pdf' ),
			'png'  => array( 'png', 'photo.PNG', 'image/png' ),
			'gif'  => array( 'gif', 'a.gif', 'image/gif' ),
			'docx' => array( 'docx', 'letter.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
		);
	}

	/** @dataProvider accepted */
	public function test_a_file_whose_bytes_prove_its_extension_is_accepted(string $kind, string $name, string $mime): void
	{
		$r = ContactAttachmentValidator::validate($this->fixture($this->dir, $kind), $name);
		$this->assertSame($mime, is_array($r) ? $r['mime'] : $r->get_error_code());
	}

	/** @return array<string, array{0:string,1:string}> kind, claimed name */
	public static function refused(): array
	{
		return array(
			'php named pdf'          => array( 'php', 'evil.php.pdf' ),
			'svg'                    => array( 'svg', 'x.svg' ),
			'html'                   => array( 'html', 'x.html' ),
			'zip named pdf'          => array( 'zip', 'x.pdf' ),
			'plain zip named docx'   => array( 'zip', 'x.docx' ),
			'png named jpg'          => array( 'png', 'photo.jpg' ),
			'pdf with no extension'  => array( 'pdf', 'offer' ),
			'pdf named exe'          => array( 'pdf', 'offer.exe' ),
		);
	}

	/** @dataProvider refused */
	public function test_a_file_whose_bytes_do_not_prove_its_extension_is_refused(string $kind, string $name): void
	{
		$r = ContactAttachmentValidator::validate($this->fixture($this->dir, $kind), $name);
		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertSame('contact_attachment_type', $r->get_error_code());
		$this->assertSame('Invalid file type.', $r->get_error_message());
	}

	public function test_the_site_widening_upload_mimes_does_not_widen_the_contact_form(): void
	{
		add_filter('upload_mimes', static fn(array $m): array => $m + array( 'svg' => 'image/svg+xml', 'html' => 'text/html' ));
		$this->assertInstanceOf(\WP_Error::class, ContactAttachmentValidator::validate($this->fixture($this->dir, 'svg'), 'x.svg'));
	}

	/** R-14: the magic-byte half of the DOC rule, measured directly (core's fileinfo half needs a real Word file). */
	public function test_doc_requires_the_ole_signature(): void
	{
		$m = new \ReflectionMethod(ContactAttachmentValidator::class, 'bytes_match');
		$m->setAccessible(true);
		$this->assertTrue($m->invoke(null, $this->fixture($this->dir, 'doc_magic'), 'doc', 'application/msword'));
		$this->assertFalse($m->invoke(null, $this->fixture($this->dir, 'pdf'), 'doc', 'application/msword'));
	}

	/** R-17: the site may narrow the seven types, never widen them. */
	public function test_a_site_that_disallows_pdf_refuses_it_here_too(): void
	{
		add_filter('upload_mimes', static function (array $m): array {
			unset($m['pdf']);
			return $m;
		});
		$this->assertInstanceOf(\WP_Error::class, ContactAttachmentValidator::validate($this->fixture($this->dir, 'pdf'), 'offer.pdf'));
	}

	public function test_a_missing_file_is_refused(): void
	{
		$this->assertInstanceOf(\WP_Error::class, ContactAttachmentValidator::validate($this->dir . '/nope', 'a.pdf'));
	}
}
