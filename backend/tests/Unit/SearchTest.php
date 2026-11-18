<?php

use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    private array $index;

    protected function setUp(): void
    {
        $this->index = build_search_index(
            [
                make_opportunity(['id' => 1, 'title' => 'Data Science Internship', 'deadline' => days_from_now(5)]),
                make_opportunity([
                    'id' => 2, 'title' => 'Arts Grant', 'category' => 'scholarship', 'interests' => ['arts'],
                    'description' => 'Funding for creative projects including data visualisation.',
                    'deadline' => days_from_now(40), 'location_mode' => 'in-person',
                ]),
                make_opportunity(['id' => 3, 'title' => 'Closed Internship', 'deadline' => days_from_now(-1)]),
                make_opportunity(['id' => 4, 'title' => 'Pending Internship', 'status' => 'pending']),
            ],
            [
                make_event(['id' => 1, 'title' => 'Robot Build Day', 'organization_name' => 'Robotics Team', 'interests' => ['engineering']]),
                make_event(['id' => 2, 'title' => 'Past Event', 'starts_at' => days_from_now(-3, '10:00'), 'ends_at' => days_from_now(-3, '11:00')]),
            ],
            [make_organization(['id' => 1, 'name' => 'Robotics Team'])],
            test_now()
        );
    }

    private function search(array $filters): array
    {
        return run_search($this->index, array_merge(EXPLORE_DEFAULTS, $filters), test_now());
    }

    private function ids(array $search): array
    {
        return array_map(fn ($r) => $r['document']['type'] . ':' . $r['document']['item']['id'], $search['results']);
    }

    public function test_text_is_normalised_and_split_into_words(): void
    {
        $this->assertSame('cafe resume', normalize_text('Café — Résumé!'));
        $this->assertSame(['art', 'pitch'], tokenize('The Art of a Pitch'));
    }

    public function test_one_letter_typos_are_detected(): void
    {
        $this->assertTrue(within_one_edit('scholarship', 'scholarshp'));
        $this->assertTrue(within_one_edit('intern', 'interm'));
        $this->assertFalse(within_one_edit('intern', 'extern'));
    }

    public function test_match_quality_levels(): void
    {
        $this->assertSame(1.0, match_quality('internship', ['internship']));
        $this->assertSame(0.7, match_quality('intern', ['internship']));
        $this->assertSame(0.4, match_quality('scholarshp', ['scholarship']));
        $this->assertSame(0.0, match_quality('robot', ['finance']));
    }

    public function test_closed_pending_and_past_items_are_not_searchable(): void
    {
        $everything = $this->sorted($this->ids($this->search([])));
        $this->assertSame(['event:1', 'opportunity:1', 'opportunity:2', 'organization:1'], $everything);
    }

    private function sorted(array $values): array
    {
        sort($values);
        return $values;
    }

    public function test_title_matches_rank_above_description_matches(): void
    {
        $result = $this->search(['q' => 'data']);
        $this->assertSame(['opportunity:1', 'opportunity:2'], $this->ids($result));
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
    }

    public function test_every_word_must_match(): void
    {
        $this->assertSame(['opportunity:1'], $this->ids($this->search(['q' => 'data internship'])));
    }

    public function test_search_covers_all_types_and_counts_them(): void
    {
        $result = $this->search(['q' => 'robot']);
        $this->assertSame(['event:1', 'organization:1'], $this->sorted($this->ids($result)));
        $this->assertSame(['all' => 2, 'opportunity' => 0, 'event' => 1, 'organization' => 1], $result['counts']);
    }

    public function test_typos_still_find_results(): void
    {
        $this->assertSame(['opportunity:1'], $this->ids($this->search(['q' => 'internshp'])));
    }

    public function test_filters_by_type_interest_location_and_date(): void
    {
        $this->assertSame(['event:1'], $this->ids($this->search(['type' => 'event'])));
        $this->assertSame(['opportunity:2'], $this->ids($this->search(['interests' => ['arts']])));
        $this->assertSame(['opportunity:2'], $this->ids($this->search(['type' => 'opportunity', 'mode' => 'in-person'])));
        $this->assertSame(['event:1', 'opportunity:1'], $this->sorted($this->ids($this->search(['within' => '7']))));
    }

    public function test_soonest_sort_puts_undated_items_last(): void
    {
        $this->assertSame(['event:1', 'opportunity:1', 'opportunity:2', 'organization:1'], $this->ids($this->search(['sort' => 'soonest'])));
    }

    public function test_no_matches_gives_an_empty_list(): void
    {
        $this->assertSame([], $this->search(['q' => 'zzzzzz'])['results']);
    }

    public function test_url_filters_are_validated(): void
    {
        $filters = read_explore_filters([
            'q' => '  robotics ', 'type' => 'opportunity', 'interest' => ['arts', 'not-real', 'arts'],
            'category' => 'internship', 'mode' => 'remote', 'within' => '7', 'sort' => 'soonest',
        ]);
        $this->assertSame(['q' => 'robotics', 'type' => 'opportunity', 'interests' => ['arts'], 'category' => 'internship', 'within' => '7', 'mode' => 'remote', 'sort' => 'soonest'], $filters);

        $bad = read_explore_filters(['type' => '<script>', 'category' => 'internship', 'within' => '999', 'sort' => 'drop table', 'interest' => 'arts']);
        $this->assertSame(array_merge(EXPLORE_DEFAULTS, ['interests' => ['arts']]), $bad);
    }

    public function test_filters_round_trip_through_the_url(): void
    {
        $filters = read_explore_filters(['q' => 'data', 'type' => 'event', 'interest' => ['arts'], 'within' => '30']);
        parse_str(explore_query_string($filters), $query);
        $this->assertSame($filters, read_explore_filters($query));
        $this->assertSame('', explore_query_string(EXPLORE_DEFAULTS));
    }
}
