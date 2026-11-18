<?php

use PHPUnit\Framework\TestCase;

final class RecommendTest extends TestCase
{
    private function score(array $item, array $profile, string $type = 'opportunity'): array
    {
        return score_item($type, $item, $profile + ['saved_categories' => []], test_now());
    }

    public function test_each_shared_interest_earns_points_and_a_reason(): void
    {
        $item = make_opportunity(['interests' => ['computer-science', 'research'], 'deadline' => null]);
        $result = $this->score($item, make_profile(['interests' => ['computer-science', 'research']]));

        $this->assertSame(6, $result['score']); // 2 interests x 3 points
        $this->assertSame('Matches your interests: Computer Science, Research', $result['reasons'][0]['text']);
    }

    public function test_interest_points_are_capped(): void
    {
        $all = ['computer-science', 'research', 'engineering', 'business'];
        $result = $this->score(make_opportunity(['interests' => $all, 'deadline' => null]), make_profile(['interests' => $all]));
        $this->assertSame(9, $result['score']); // capped at 3 interests x 3 points
    }

    public function test_deadlines_three_to_fourteen_days_away_score_highest(): void
    {
        $score = fn (int $days) => $this->score(make_opportunity(['deadline' => days_from_now($days)]), make_profile())['score'];
        $this->assertSame(2, $score(7));
        $this->assertSame(1, $score(1));
        $this->assertSame(1, $score(20));
        $this->assertSame(0, $score(60));
    }

    public function test_followed_organizations_add_points(): void
    {
        $event = make_event(['starts_at' => days_from_now(30, '10:00'), 'ends_at' => days_from_now(30, '11:00')]);
        $result = $this->score($event, make_profile(['followed' => [1]]), 'event');
        $this->assertSame(2, $result['score']);
        $this->assertStringContainsString('Org A Society', $result['reasons'][0]['text']);
    }

    public function test_missing_eligibility_is_penalised(): void
    {
        $result = $this->score(make_opportunity(['eligibility' => '', 'deadline' => null]), make_profile());
        $this->assertSame(-1, $result['score']);
    }

    public function test_nothing_is_recommended_without_interests_or_follows(): void
    {
        $this->assertSame([], recommend([make_opportunity()], [make_event()], make_profile(), test_now()));
    }

    public function test_changing_interests_changes_the_recommendations(): void
    {
        $cs = make_opportunity(['id' => 1, 'interests' => ['computer-science'], 'deadline' => null]);
        $arts = make_opportunity(['id' => 2, 'interests' => ['arts'], 'deadline' => null]);

        $forCs = recommend([$cs, $arts], [], make_profile(['interests' => ['computer-science']]), test_now());
        $forArts = recommend([$cs, $arts], [], make_profile(['interests' => ['arts']]), test_now());

        $this->assertSame([1], array_map(fn ($r) => $r['item']['id'], $forCs));
        $this->assertSame([2], array_map(fn ($r) => $r['item']['id'], $forArts));
    }

    public function test_closed_unpublished_saved_and_past_items_are_excluded(): void
    {
        $result = recommend(
            [
                make_opportunity(['id' => 1, 'deadline' => days_from_now(-1)]),
                make_opportunity(['id' => 2, 'status' => 'pending']),
                make_opportunity(['id' => 3]),
                make_opportunity(['id' => 4]),
            ],
            [make_event(['id' => 9, 'starts_at' => days_from_now(-2, '10:00'), 'ends_at' => days_from_now(-2, '11:00')])],
            make_profile(['interests' => ['computer-science'], 'saved_opportunity_ids' => [3]]),
            test_now()
        );
        $this->assertSame([4], array_map(fn ($r) => $r['item']['id'], $result));
    }

    public function test_items_similar_to_saved_ones_get_a_boost(): void
    {
        $saved = make_opportunity(['id' => 1, 'category' => 'internship']);
        $same = make_opportunity(['id' => 2, 'category' => 'internship', 'deadline' => null]);
        $other = make_opportunity(['id' => 3, 'category' => 'scholarship', 'deadline' => null]);

        $result = recommend([$saved, $other, $same], [], make_profile(['interests' => ['computer-science'], 'saved_opportunity_ids' => [1]]), test_now());

        $this->assertSame(2, $result[0]['item']['id']);
        $this->assertSame(1, $result[0]['score'] - $result[1]['score']);
    }

    public function test_ties_are_broken_by_soonest_date_then_title(): void
    {
        $later = make_opportunity(['id' => 1, 'title' => 'B', 'deadline' => days_from_now(10)]);
        $sooner = make_opportunity(['id' => 2, 'title' => 'A', 'deadline' => days_from_now(9)]);
        $result = recommend([$later, $sooner], [], make_profile(['interests' => ['computer-science']]), test_now());
        $this->assertSame([2, 1], array_map(fn ($r) => $r['item']['id'], $result));
    }

    public function test_the_limit_is_respected(): void
    {
        $many = array_map(fn ($i) => make_opportunity(['id' => $i, 'title' => "O$i"]), range(1, 10));
        $this->assertCount(3, recommend($many, [], make_profile(['interests' => ['computer-science']]), test_now(), 3));
    }
}
