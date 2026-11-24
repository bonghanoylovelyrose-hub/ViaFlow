<?php

namespace Tests\Unit;

use App\Services\IncidentClassificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IncidentClassificationServiceTest extends TestCase
{
    #[DataProvider('reports')]
    public function test_classification_uses_severity_evidence(string $category, string $description, string $expected, ?string $type = null): void
    {
        $classification = (new IncidentClassificationService)->classify($category, $description, $type);

        $this->assertSame($expected, $classification['priority']);
        $this->assertSame($expected, $classification['automatic_priority']);
        $this->assertNotEmpty($classification['classification_reason']);
    }

    public static function reports(): array
    {
        return [
            'minor leak' => ['Water Leak', 'A dripping faucet at my house.', 'Non-Urgent'],
            'significant leak' => ['Water Leak', 'There is a significant water leak near the corner.', 'Urgent'],
            'several households' => ['Water Leak', 'Multiple affected households reported the same problem.', 'Urgent'],
            'burst water main' => ['Water Leak', 'The main pipe has burst and is flooding homes.', 'Urgent'],
            'negated burst' => ['Water Leak', 'Not a burst pipe, just a small leak at the tap.', 'Non-Urgent'],
            'isolated interruption' => ['No Water Supply', 'Only our house has no water.', 'Non-Urgent'],
            'shared interruption' => ['No Water Supply', 'No water supply for several households.', 'Urgent'],
            'unaffected neighbours' => ['No Water Supply', 'Only our house has no water. Several households nearby still have water.', 'Non-Urgent'],
            'widespread interruption with no danger' => ['No Water Supply', 'The entire neighborhood has no water but there is no immediate health risk.', 'Urgent'],
            'widespread water emergency' => ['No Water Supply', 'The entire community has no water and faces an immediate health hazard.', 'Urgent'],
            'hazard with no reported scope' => ['No Water Supply', 'There is an immediate health risk.', 'Needs Review'],
            'minor discoloration' => ['Water Quality Issue', 'No signs of contamination, slight cloudiness at the tap.', 'Non-Urgent'],
            'odor' => ['Water Quality Issue', 'There is an unusual odor whenever the tap is open.', 'Urgent'],
            'unusual odor with negated discoloration' => ['Water Quality Issue', 'The water isn’t discolored but has an unusual odor.', 'Urgent'],
            'contamination' => ['Water Quality Issue', 'Suspected contamination in the drinking water.', 'Urgent'],
            'unsafe drinking water' => ['Water Quality Issue', 'The water is not safe to drink.', 'Urgent'],
            'negated hazards list' => ['Water Quality Issue', 'No leaks, flooding, contamination or immediate safety hazards were detected.', 'Needs Review'],
            'routine meter issue' => ['Meter Concern', 'The meter reading is inaccurate.', 'Non-Urgent'],
            'meter interruption' => ['Meter Concern', 'A service interruption since the meter was replaced.', 'Urgent'],
            'meter safety hazard' => ['Meter Concern', 'Exposed live wires near the meter box.', 'Urgent'],
            'meter history fixed' => ['Meter Concern', 'The significant meter leak was fixed. Please check our meter.', 'Non-Urgent'],
            'other type supplies severity' => ['Others', 'Several households affected.', 'Urgent', 'Major pipe burst'],
            'other description negates type' => ['Others', 'Not a main pipe burst, just a minor leak.', 'Non-Urgent', 'Possible main pipe burst'],
            'other insufficient detail' => ['Others', 'Please send someone to check.', 'Needs Review', 'Low pressure'],
            'category alone insufficient' => ['Water Leak', 'Please help.', 'Needs Review'],
            'Cebuano faucet drip' => ['Water Leak', 'Nagtulo ra ang gripo.', 'Non-Urgent'],
            'Filipino significant leak' => ['Water Leak', 'Malakas ang tagas sa tubo sa harap ng bahay.', 'Urgent'],
            'mixed Cebuano major burst' => ['Water Leak', 'Nibuto ang main pipe. Gibaha ang dalan.', 'Urgent'],
            'Cebuano negated burst' => ['Water Leak', 'Walay nibuto nga tubo, gamay ra nga tagas.', 'Non-Urgent'],
            'Cebuano isolated outage' => ['No Water Supply', 'Walay tubig sa among balay ra.', 'Non-Urgent'],
            'Filipino shared outage' => ['No Water Supply', 'Walang tubig sa tatlong bahay.', 'Urgent'],
            'Cebuano widespread outage without danger' => ['No Water Supply', 'Ang tibuok barangay walay tubig pero walay peligro sa panglawas.', 'Urgent'],
            'Filipino widespread dangerous outage' => ['No Water Supply', 'Walang tubig sa buong barangay at delikado sa kalusugan.', 'Urgent'],
            'Cebuano outage without details' => ['No Water Supply', 'Walay tubig. Palihog tan-awa.', 'Needs Review'],
            'Cebuano very dirty water' => ['Water Quality Issue', 'Ang tubig is hugaw kaayo.', 'Urgent'],
            'Cebuano dirty water without severity' => ['Water Quality Issue', 'Hugaw ang tubig.', 'Needs Review'],
            'Filipino mild appearance with negation' => ['Water Quality Issue', 'Hindi kontaminado ang tubig, medyo malabo lang.', 'Non-Urgent'],
            'Filipino unsafe drinking water' => ['Water Quality Issue', 'Ang tubig ay hindi ligtas inumin.', 'Urgent'],
            'Filipino illness after drinking' => ['Water Quality Issue', 'Nagsuka kami pagkatapos ng pag-inom ng tubig.', 'Urgent'],
            'unrelated illness' => ['Water Quality Issue', 'Nagsuka kami pagkatapos ng pagtakbo.', 'Needs Review'],
            'Cebuano meter reading' => ['Meter Concern', 'Sayop ang basa sa among metro.', 'Non-Urgent'],
            'Filipino meter interruption' => ['Meter Concern', 'Naputol ang tubig matapos palitan ang metro.', 'Urgent'],
            'Filipino meter hazard' => ['Meter Concern', 'May nakalantad na kableng may kuryente malapit sa metro.', 'Urgent'],
            'Filipino repaired leak' => ['Meter Concern', 'Malakas ang tagas naayos na. Pakisuri ang metro.', 'Non-Urgent'],
            'Others with local type and reported severity' => ['Others', 'SobranG dumi pag bukas ng gripo.', 'Urgent', 'Maruming tubig'],
            'Others with severity in local type' => ['Others', 'Pakipuntahan ang kanto.', 'Urgent', 'Pumutok ang pangunahing tubo'],
            'Others with local type contradicted by description' => ['Others', 'Walay nibuto nga tubo, gamay ra nga tagas.', 'Non-Urgent', 'Nibuto ang main pipe'],
            'Others without enough detail' => ['Others', 'Pakitingnan po.', 'Needs Review', 'Mahinang agas'],
        ];
    }
}
