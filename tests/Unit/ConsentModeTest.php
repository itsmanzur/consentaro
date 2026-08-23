<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Unit;

use Consentaro\Consent\ConsentMode;
use Consentaro\Tests\TestCase;

final class ConsentModeTest extends TestCase {

	public function test_sanitize_states_drops_unknown_keys(): void {
		$mode = new ConsentMode();

		$result = $mode->sanitizeStates(
			array(
				'ad_storage'      => 'granted',
				'not_a_real_type' => 'granted',
			)
		);

		$this->assertArrayNotHasKey( 'not_a_real_type', $result );
		$this->assertSame( array_keys( $result ), ConsentMode::TYPES );
	}

	public function test_sanitize_states_only_accepts_literal_granted_string(): void {
		$mode = new ConsentMode();

		$result = $mode->sanitizeStates(
			array(
				'ad_storage'              => 'granted',
				'analytics_storage'       => 'GRANTED', // wrong case — must not pass.
				'ad_user_data'            => true, // truthy but not the literal string — must not pass.
				'ad_personalization'      => 1,
				'functionality_storage'   => 'yes',
				'personalization_storage' => 'denied',
				// security_storage omitted entirely — must default to denied.
			)
		);

		$this->assertSame( 'granted', $result['ad_storage'] );
		$this->assertSame( 'denied', $result['analytics_storage'] );
		$this->assertSame( 'denied', $result['ad_user_data'] );
		$this->assertSame( 'denied', $result['ad_personalization'] );
		$this->assertSame( 'denied', $result['functionality_storage'] );
		$this->assertSame( 'denied', $result['personalization_storage'] );
		$this->assertSame( 'denied', $result['security_storage'] );
	}

	public function test_default_states_have_correct_granted_denied_split(): void {
		$mode    = new ConsentMode();
		$default = $mode->getDefaultStates();

		$this->assertSame(
			array(
				'ad_storage'              => 'denied',
				'analytics_storage'       => 'denied',
				'ad_user_data'            => 'denied',
				'ad_personalization'      => 'denied',
				'functionality_storage'   => 'granted',
				'personalization_storage' => 'denied',
				'security_storage'        => 'granted',
			),
			$default
		);
	}

	public function test_all_granted_preset(): void {
		$mode = new ConsentMode();
		$all  = $mode->allGranted();

		foreach ( ConsentMode::TYPES as $type ) {
			$this->assertSame( 'granted', $all[ $type ] );
		}
	}
}
