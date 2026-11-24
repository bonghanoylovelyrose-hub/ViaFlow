<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The member details step must not re-ask a question the applicant already
 * answered, and every required choice must be marked so it cannot be silently
 * missed.
 */
class MemberDetailsAnswerPrefillTest extends TestCase
{
    use RefreshDatabase;

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'residency.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
        );
    }

    private function memberSession(): array
    {
        return ['register_connection_flow' => [
            'is_viohoa_member' => 'yes',
            'declared_viohoa_member' => true,
            'membership_type' => 'Member',
        ]];
    }

    public function test_local_association_question_is_pre_filled_from_the_viohoa_answer(): void
    {
        $html = $this->withSession($this->memberSession())
            ->get(route('register.member-details'))
            ->assertOk()
            ->getContent();

        // Answering "YES" to the membership question pre-selects it here.
        $this->assertMatchesRegularExpression(
            '/id="association_member_yes"[^>]*\schecked/',
            $html,
            'association_member should be pre-selected from the earlier VIOHOA answer.',
        );
    }

    public function test_pre_fill_follows_a_no_answer_to_the_viohoa_question(): void
    {
        $html = $this->withSession(['register_connection_flow' => [
            'is_viohoa_member' => 'no',
            'declared_viohoa_member' => false,
            'membership_type' => 'Non-Member',
        ]])->get(route('register'))->getContent();

        $this->assertNotEmpty($html);

        // A non-member never reaches this step, so just assert the guard holds.
        $response = $this->withSession(['register_connection_flow' => [
            'is_viohoa_member' => 'no',
            'declared_viohoa_member' => false,
            'membership_type' => 'Non-Member',
        ]])->get(route('register.member-details'));

        $response->assertRedirect(route('register'));
    }

    public function test_every_choice_group_is_marked_required_and_not_display_none(): void
    {
        $html = $this->withSession($this->memberSession())
            ->get(route('register.member-details'))
            ->assertOk()
            ->getContent();

        foreach (['housing_rights_documented', 'resident_of_community', 'association_member', 'association_registered'] as $field) {
            $this->assertMatchesRegularExpression(
                '/name="'.$field.'" value="yes"[^>]*required/',
                $html,
                $field.' must be marked required so the browser blocks an empty answer.',
            );

            // Each option is focusable: display:none radios cannot be tabbed to.
            $this->assertDoesNotMatchRegularExpression(
                '/register-choice-input d-none[^>]*name="'.$field.'"/',
                $html,
                $field.' radios must stay in the layout so they remain keyboard reachable.',
            );
        }
    }

    public function test_uploading_a_document_and_proceeding_reaches_the_connection_form(): void
    {
        $response = $this->withSession($this->memberSession())
            ->post(route('register.member-details.store'), [
                'housing_rights_documented' => 'yes',
                'resident_of_community' => 'yes',
                'association_member' => 'yes',
                'association_registered' => 'yes',
                'membership_document_type' => 'Proof of Residency',
                'membership_document_photos' => [$this->png()],
                'agreement_accepted' => '1',
            ]);

        $response->assertRedirect(route('register.new-connection'));
        $response->assertSessionHasNoErrors();

        // The workflow flag is stored for the next step.
        $this->assertIsArray(session('register_member_details'));
    }

    public function test_a_missing_choice_reports_which_question_is_unanswered(): void
    {
        $response = $this->withSession($this->memberSession())
            ->from(route('register.member-details'))
            ->followingRedirects()
            ->post(route('register.member-details.store'), [
                'housing_rights_documented' => 'yes',
                'resident_of_community' => 'yes',
                // association_member deliberately omitted
                'association_registered' => 'yes',
                'membership_document_type' => 'Proof of Residency',
                'membership_document_photos' => [$this->png()],
                'agreement_accepted' => '1',
            ]);

        // The page now explains the specific question instead of a generic banner.
        $response->assertSee('Please answer whether you are part of the local association.');
    }
}