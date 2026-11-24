<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_selection_and_back_destinations_follow_the_chosen_path(): void
    {
        $this->post(route('register.type.store'), ['is_viohoa_member' => 'yes'])->assertRedirect(route('register.member-details'));
        $this->get(route('register.member-details'))->assertOk()->assertSee('data-registration-back', false)->assertSee(route('register'));
        $this->withSession(['register_member_details' => ['agreement_accepted' => true]])
            ->get(route('register.new-connection'))->assertOk()->assertSee(json_encode(route('register.member-details')), false);
        $this->post(route('register.type.store'), ['is_viohoa_member' => 'no'])->assertRedirect(route('register.new-connection'));
        $this->assertNull(session('register_member_details'));
        $this->get(route('register'))->assertSee('value="no" checked', false)->assertDontSee('window.history.back()', false);
        $this->get(route('register.new-connection'))->assertOk()->assertSee('window.location.href = '.json_encode(route('register')), false);
        $this->get(route('register.member-details'))->assertRedirect(route('register'));
    }
}
