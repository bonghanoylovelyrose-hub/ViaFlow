<?php

namespace App\Services;

use App\Models\Incident;

class IncidentClassificationService
{
    private const SAFETY = '\b(?:immediate (?:health (?:(?:or|and) safety )?|safety )?(?:risk|hazard|danger)s?|(?:serious |direct )?(?:health|safety|electrical) hazards?|life[- ]threatening(?: danger| conditions?)?|electrocution(?: risk)?|exposed (?:live )?(?:wires|power lines)|live (?:electrical )?wires|dangerous flooding|people (?:are )?trapped'
        .'|(?:peligro|delikado) (?:sa|para sa) (?:kinabuhi|buhay|panglawas|kalusugan|kaligtasan)|(?:nakuryente|nakukuryente)|(?:nakalantad|hayag) (?:na|nga) (?:live wire|kable nga naay kuryente|kableng may kuryente))\b';

    private const SUPPLY_LOSS = '(?:(?:no|without) (?:running )?water(?: supply)?|(?:wa(?:la)?y?|walang|wala pong) (?:tubig|agas|daloy ng tubig)|wala (?:mi|kaming|silang) (?:tubig|agas))';

    private const NEGATION = '\b(?:no signs of|no|not(?! only\b)|never|without|neither|denies|ruled out|wala(?:y|ng)?|dili(?! (?:lang|ra)\b)|hindi(?! (?:lang|lamang)\b)|di|walay timailhan sa|walang senyales ng)\b';

    private const LOCAL_HOUSEHOLDS = '(?:panimalay|balay|silingan|kabahayan|bahay|sambahayan|pamilya)';

    private const LOCAL_AFFECTED = '(?:(?:ang )?(?:apektado|naapektuhan)|'.self::SUPPLY_LOSS.')';

    private const LOCAL_PIPE_FAILURE = '(?:nibuto|nabuak|nabuslot|pumutok|sumabog) (?:ang |nga )?';

    private const LOCAL_MAIN_PIPE = '(?:main (?:water )?pipe|pangunang tubo|pangunahing tubo|dakong tubo|malaking tubo)';

    private const HOUSEHOLD_COUNT = '(?:several|multiple|many|numerous|two|three|four|five|[2-9]|[1-9]\d+)';

    private const HOUSEHOLDS = '(?:households?|houses?|homes?|neighbou?rs?|families|residents)';

    private const AFFECTED = '(?:(?:are |were |have been )?affected|(?:are )?without (?:running )?water|(?:have|had|with) no (?:running )?water|(?:report|reported|reporting) (?:the same (?:issue|problem)|no water|a leak))';

    private const MULTIPLE_HOUSEHOLDS = '\b(?:'
        .self::HOUSEHOLD_COUNT.'\s+(?:complaints|affected\s+'.self::HOUSEHOLDS.'|'.self::HOUSEHOLDS.'\s+'.self::AFFECTED.')'
        .'|(?:(?:affects?|affected|impacting|impacts?)\s+|(?:no (?:running )?water(?: supply)?|outage|leak)\s+(?:at|in|for|across)\s+)(?:at least\s+)?'.self::HOUSEHOLD_COUNT.'\s+'.self::HOUSEHOLDS
        .'|(?:daghang|daghan (?:nga|ka)|maraming|marami(?:ng)? (?:mga )?|ilang|tatlong|tatlo ka|tulo ka|duha ka|dalawang|[2-9]\d*)\s+'.self::LOCAL_HOUSEHOLDS.'\s+'.self::LOCAL_AFFECTED
        .'|'.self::LOCAL_AFFECTED.'\s+(?:ang |sa )?(?:daghang|maraming|ilang|tatlong|tulo ka|duha ka|dalawang)\s+'.self::LOCAL_HOUSEHOLDS.')\b';

    private const AREA = '(?:(?:whole|entire)\s+(?:neighbou?rhood|community|street|area|block|purok)|all (?:the )?(?:homes|houses|households))';

    private const WIDESPREAD = '\b(?:(?:widespread|area[- ]wide|community[- ]wide|neighbou?rhood[- ]wide)\s+(?:water (?:supply )?)?(?:outage|emergency|interruption|shortage|problem)|(?:outage|interruption|shortage)\s+(?:is\s+)?widespread|major water emergency|'
        .self::AREA.'\s+(?:has no water|is affected|is without water|'.self::AFFECTED.')'
        .'|(?:no water(?: supply)?|outage|leak|flooding)\s+(?:for|in|across|throughout)\s+(?:the\s+)?'.self::AREA
        .'|(?:tibuok|buong) (?:purok|barangay|komunidad|lugar)\s+'.self::LOCAL_AFFECTED
        .'|'.self::LOCAL_AFFECTED.'\s+(?:sa |ang )?(?:tibuok|buong) (?:purok|barangay|komunidad|lugar))\b';

    private const CONTEXT = [
        'Water Quality Issue' => '\b(?:contaminat\w*|water quality|discolor\w*|discolour\w*|taste|odou?r|sewage|cloudy water|brown water|(?:unsafe|not safe) to drink|hugaw|dumi|marumi|kontaminado|mabaho|baho|malabo|lapok|putik|(?:dili|hindi) (?:luwas|ligtas) (?:imnon|inumin))\b',
        'Meter Concern' => '\b(?:meter|metro|kontador|reading discrepanc\w*)\b',
        'No Water Supply' => '\b(?:'.self::SUPPLY_LOSS.'|outage|water (?:supply )?(?:interruption|shortage|emergency)|(?:water|supply) (?:is )?cut off|no supply)\b',
        'Water Leak' => '\b(?:leak\w*|dripping|burst|pipe break|broken (?:water )?pipe|flood\w*|tagas|tulo|tumutulo|nagtulo|gapatak|baha|pagbaha|'.self::LOCAL_PIPE_FAILURE.'(?:tubo|'.self::LOCAL_MAIN_PIPE.'))\b',
    ];

    private const MAJOR_INCIDENT_RULE = 'major_incident';

    private const RULES = [
        'Water Leak' => [
            self::MAJOR_INCIDENT_RULE => [
                '\b(?:(?:water main|main (?:water )?pipe|major pipe|mainline)(?:\s+(?:has|had|is|was))?\s+(?:burst|ruptur\w*|broken|break)|(?:burst|ruptur\w*|broken)\s+(?:water main|main (?:water )?pipe|major pipe|mainline)|major pipe (?:break|burst)|(?:severe|dangerous|rapid) flood\w*|flood\w*\s+(?:homes?|houses?|roads?)'
                    .'|'.self::LOCAL_PIPE_FAILURE.self::LOCAL_MAIN_PIPE.'|(?:grabe(?:ng)?|malubhang|delikadong) (?:pagbaha|baha)|(?:gibaha|binaha) (?:ang )?(?:mga )?(?:balay|bahay|dalan|kalsada))\b',
                'A major pipe failure or dangerous flooding was reported.',
            ],
            Incident::PRIORITY_URGENT => [
                '\b(?:(?:significant|severe|substantial|large|heavy|major|uncontrolled)\s+(?:water\s+)?leak\w*|(?:water\s+)?(?:leak|leaks)\s+(?:is\s+|are\s+)?(?:significant|severe|substantial|large|heavy)|water\s+(?:is\s+)?(?:gushing|pouring)|burst\s+pipe|pipe\s+(?:has\s+)?burst'
                    .'|(?:kusog|grabe|dako|lakas|malakas|malaki)(?: kaayo| gyud| na| nga|ng)? (?:ang )?(?:tagas|tulo|leak)|(?:tagas|tulo|leak) (?:kay )?(?:kusog|grabe|dako|malakas|malaki)|'.self::LOCAL_PIPE_FAILURE.'tubo|(?:bumubulwak|nagbugwak) (?:ang )?tubig)\b',
                'A significant leak or pipe break was reported.',
            ],
            Incident::PRIORITY_NON_URGENT => [
                '\b(?:(?:minor|small|slight|slow)\s+(?:water\s+)?(?:leak\w*|drip\w*)|dripping (?:faucet|tap)|(?:faucet|tap)\s+(?:is\s+)?dripping|(?:a\s+)?few drops'
                    .'|(?:gamay|gagmay|maliit|kaunti|konti)(?: ra| lang| nga| na|ng)? (?:nga |na |ang )?(?:tagas|tulo|leak)|(?:gapatak|nagpatak|nagtulo|nagatulo|pumatak|tumutulo) (?:ra|lang|lamang|(?:ang |sa )?(?:gripo|faucet|tap)))\b',
                'The report describes a minor leak or drip.',
            ],
        ],
        'No Water Supply' => [
            Incident::PRIORITY_NON_URGENT => [
                '\b(?:(?:minor|brief|temporary|isolated)\s+(?:water\s+)?(?:interruption|outage|supply issue)|(?:only|just)\s+(?:my|our|one|a single)\s+(?:house|home|household)(?:\s+is)?\s+(?:affected|without water|has no water)|(?:one|a single)\s+(?:house|home|household)\s+(?:affected|without water)|(?:no water|water went off)\s+(?:for\s+)?(?:a few|several)\s+minutes'
                    .'|'.self::SUPPLY_LOSS.' (?:sa (?:amo|among|amin|aming|aming bahay|among balay)(?: balay| bahay)? (?:ra|lang|lamang)|(?:saglit|sandali|kadiyot|kadali) (?:ra|lang|lamang))|(?:amo(?:ng)?|amin(?:g)?|usa ka|isang) (?:balay|bahay) (?:ra|lang|lamang) (?:ang )?'.self::LOCAL_AFFECTED.')\b',
                'The supply interruption was described as brief or isolated.',
            ],
        ],
        'Water Quality Issue' => [
            self::MAJOR_INCIDENT_RULE => [
                '\b(?:contaminat(?:ed|ion|es)|water (?:is )?(?:unsafe|not safe) to drink|(?:sewage|chemicals?|fuel|gasoline|bacteria)\s+(?:in|mixed with)\s+(?:the |our )?water|(?:sick|ill|vomiting)\s+after drinking'
                    .'|kontaminado|kontaminasyon|(?:dili|hindi) (?:luwas|ligtas) (?:nga |na )?(?:imnon|inumin)|(?:naay|may|mayroong) (?:bakterya|kemikal|gasolina|tubig sa kanal) (?:sa|nga nasagol sa|na humalo sa) (?:among |aming |inom nga )?tubig|(?:nagsuka|nagsusuka|nagsakit|nagkasakit) (?:kami |mi )?(?:human|pagkatapos|matapos) (?:ug |ng )?(?:uminom|inom|pag-inom))\b',
                'The report indicates possible water contamination or illness.',
            ],
            Incident::PRIORITY_URGENT => [
                '\b(?:(?:significant|severe|strong|heavy|marked|dark)\s+(?:water\s+)?(?:discolou?r\w*|cloudiness)|(?:unusual|strong|foul|bad|unpleasant)\s+(?:smell|odou?r)|(?:water|tap water)\s+(?:smells|has a smell)\s+(?:odd|bad|unusual|foul)|(?:brown|black|reddish|rust[- ]colou?red)\s+water|water\s+(?:is|turned|looks)\s+(?:brown|black|reddish)|rotten egg smell'
                    .'|(?:hugaw|baho|lubog|lapok|malubog) (?:kaayo|gyud|jud|kaau)|grabe (?:ka)?(?:hugaw|baho)|(?:sobrang|napaka) ?(?:dumi|baho|labo)|(?:maruming|mabahong|maitim na|kulay kalawang na) tubig|(?:marumi|maitim|mabaho|kulay kalawang) (?:ang )?tubig|(?:tubig|water) (?:ay |kay |is )?(?:mabaho|maitim|kulay kalawang)|(?:lahi|kakaiba) (?:ang )?(?:bahô|baho|amoy))\b',
                'The report describes marked discoloration or an unusual odor.',
            ],
            Incident::PRIORITY_NON_URGENT => [
                '\b(?:(?:minor|small|slight(?:ly)?|mild(?:ly)?|faint|light)\s+(?:change in\s+)?(?:water\s+)?(?:appearance|taste|discolou?r\w*|cloudiness|cloudy)|(?:water|tap water)\s+tastes\s+(?:a little\s+|slightly\s+)?(?:different|odd|unusual)|different taste'
                    .'|(?:medyo|gamay(?: ra)?|bahagyang) (?:malabo|malubog|lubog|lapok|maiba ang lasa)|(?:lahi|iba) (?:gamay|ra|lang|nang kaunti) (?:ang )?lasa|(?:kaunting pagbabago|bahagyang pagbabago) sa (?:kulay|lasa))\b',
                'The report describes a minor appearance or taste concern.',
            ],
        ],
        'Meter Concern' => [
            Incident::PRIORITY_URGENT => [
                '\b(?:(?:significant|severe|substantial|large|heavy|major)\s+(?:meter[- ]related\s+|meter\s+|water\s+)?leak\w*|water\s+(?:is\s+)?(?:gushing|pouring)|service\s+interruption|'.self::SUPPLY_LOSS.'|(?:water|supply) (?:has )?(?:stopped|been cut off)|loss of (?:water )?(?:service|supply)'
                    .'|(?:kusog|dako|grabe|malaki|malakas)(?: kaayo|ng| na)? (?:ang )?(?:tagas|tulo|leak)|(?:nawala|naundang|nahunong|naputol) (?:ang )?(?:tubig|suplay))\b',
                'The meter concern involves significant water loss or interrupted service.',
            ],
            Incident::PRIORITY_NON_URGENT => [
                '\b(?:routine\s+(?:meter\s+)?(?:inspection|check)|meter\s+(?:inspection|reading\s+discrepanc\w*)|(?:incorrect|wrong|inaccurate|disputed)\s+(?:water\s+)?(?:meter\s+)?reading|reading\s+(?:is\s+)?(?:off|inaccurate|incorrect|wrong)|(?:check|inspect)\s+(?:my|our|the)\s+meter'
                    .'|(?:sayop|mali|dili sakto) (?:man )?(?:ang )?(?:basa|reading|numero)|(?:basa|reading|numero) (?:kay |ay )?(?:sayop|mali|dili sakto)|(?:pa[ -]?check|paki[ -]?check|pakisuri|ipasusi) (?:lang |ra )?(?:ang |sa )?(?:meter|metro|kontador))\b',
                'The report describes a routine meter check or a reading discrepancy.',
            ],
        ],
        'Others' => [
            Incident::PRIORITY_URGENT => [
                '\b(?:significant service interruption|major water loss|substantial water loss|(?:grabe(?:ng)?|sobrang|matinding) (?:kawala sa tubig|pagkawala ng tubig|kahuyang sa agas|hina ng agas))\b',
                'The specified incident involves significant water loss or interrupted service.',
            ],
            Incident::PRIORITY_NON_URGENT => [
                '\b(?:minor inconvenience|isolated concern|routine inspection|brief interruption|(?:gamay ra|gamay nga|maliit na) (?:problema|abala)|(?:sandali|kadiyot) (?:lang|ra) (?:ang )?(?:abala|problema))\b',
                'The specified incident was described as minor or isolated.',
            ],
        ],
    ];

    /** @return array{priority: string, automatic_priority: string, classification_reason: string} */
    public function classify(string $category, string $description, ?string $specifiedType = null): array
    {
        $clauses = $this->clauses($description);
        $categories = [$category];
        if ($category === 'Others') {
            $context = array_merge($this->clauses($specifiedType ?? ''), $clauses);
            $categories = array_keys(array_filter(self::CONTEXT, fn (string $pattern) => $this->indicator($pattern, $context) !== null));
            $categories = $categories ?: ['Others'];
        }

        $result = $this->assess($categories, $clauses);
        if ($category === 'Others' && $specifiedType && ! preg_match('~'.self::NEGATION.'|\b(?:false alarm|fixed|repaired|resolved|naayo|naayos|na-repair)\b~iu', $this->withoutSupplyLoss($description))) {
            $typeResult = $this->assess($categories, $this->clauses($specifiedType));
            if ($typeResult && (! $result || array_search($typeResult['priority'], Incident::PRIORITY_ORDER, true) < array_search($result['priority'], Incident::PRIORITY_ORDER, true))) {
                $result = $typeResult;
            }
        }

        return $result ?? $this->result(Incident::PRIORITY_NEEDS_REVIEW, 'The report does not provide enough detail about severity, affected households, or safety risks.');
    }

    private function assess(array $categories, array $clauses): ?array
    {
        $hazard = $this->indicator(self::SAFETY, $clauses);
        $widespread = $this->indicator(self::WIDESPREAD, $clauses);
        $supplyOnly = $categories === ['No Water Supply'];

        if ($hazard && (! $supplyOnly || $widespread)) {
            return $this->result(Incident::PRIORITY_URGENT, $supplyOnly
                ? 'A widespread water interruption with an immediate health or safety risk was reported.'
                : 'An immediate health or safety hazard was reported.', $hazard);
        }

        foreach ([self::MAJOR_INCIDENT_RULE, Incident::PRIORITY_URGENT, Incident::PRIORITY_NON_URGENT] as $rule) {
            if ($rule === Incident::PRIORITY_URGENT) {
                if ($hazard && $supplyOnly) {
                    return $this->result(Incident::PRIORITY_NEEDS_REVIEW, 'A potential hazard was reported, but the scope of the water interruption is unclear.', $hazard);
                }

                $households = $this->indicator(self::MULTIPLE_HOUSEHOLDS, $clauses) ?? $widespread;
                if ($households && array_intersect($categories, ['Water Leak', 'No Water Supply', 'Water Quality Issue'])) {
                    return $this->result(Incident::PRIORITY_URGENT, 'Multiple households or complaints were reported.', $households);
                }
            }

            foreach ($categories as $category) {
                [$pattern, $reason] = self::RULES[$category][$rule] ?? [null, null];
                if ($pattern && ($indicator = $this->indicator($pattern, $clauses))) {
                    $priority = $rule === self::MAJOR_INCIDENT_RULE ? Incident::PRIORITY_URGENT : $rule;

                    return $this->result($priority, $reason, $indicator);
                }
            }
        }

        return null;
    }

    private function clauses(string $text): array
    {
        $text = (string) preg_replace('~n[’\x27]t\b~iu', ' not', $text);
        $text = (string) preg_replace('~[^\S\r\n]+~u', ' ', $text);

        return preg_split('~[.!?;:\r\n]+|\b(?:but|however|although|though|instead|yet|pero|apan|ngunit|subalit|kundi)\b|,(?=\s+(?:only|just|minor|small|slight(?:ly)?|mild(?:ly)?|a few|gamay|medyo|bahagyang|konti|kaunti)\b)~iu', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function withoutSupplyLoss(string $text): string
    {
        // Phrases like "walay tubig" report an outage, so they must not negate nearby evidence.
        return (string) preg_replace('~\b'.self::SUPPLY_LOSS.'\b~iu', 'outage', $text);
    }

    private function indicator(string $pattern, array $clauses): ?string
    {
        foreach ($clauses as $clause) {
            preg_match_all('~'.$pattern.'~iu', $clause, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [$phrase, $offset]) {
                $before = $this->withoutSupplyLoss(substr($clause, 0, $offset));
                $negated = preg_match('~'.self::NEGATION.'[\s,]+(?:(?!\b(?:is|are|was|were|has|have|caused|reported|may|naay|adunay|merong|mayroong)\b)[\p{L}\p{N}’\x27-]+[\s,]+){0,6}$~iu', $before);
                $after = substr($clause, $offset + strlen($phrase));
                $resolved = preg_match('~^\s+(?:(?:is|was|were|has been|have been)\s+)?(?:ruled out|not (?:present|reported|detected|affected)|repaired|fixed|resolved|(?:naayo|naayos|na-repair)(?: na)?|wala na|(?:hindi|dili) na (?:apektado|nakita))\b~iu', $after);
                if (! $negated && ! $resolved) {
                    return trim($phrase);
                }
            }
        }

        return null;
    }

    private function result(string $priority, string $reason, ?string $indicator = null): array
    {
        return [
            'priority' => $priority,
            'automatic_priority' => $priority,
            'classification_reason' => $indicator ? $reason.' Indicator: “'.mb_strimwidth($indicator, 0, 120, '…').'”.' : $reason,
        ];
    }
}
