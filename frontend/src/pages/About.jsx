/*
 * About CampusBridge: the problem, how recommendations work, privacy.
 */
import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { Notice, PageHeader } from '../components/ui.jsx';
import { useTitle } from '../hooks.js';
import { useSession } from '../session.jsx';

export function About() {
    useTitle('About');
    const { meta } = useSession();
    const { hash } = useLocation();

    // Links such as /about#privacy jump to that section.
    useEffect(() => {
        if (hash) {
            document.getElementById(hash.slice(1))?.scrollIntoView();
        }
    }, [hash]);

    return (
        <div className="container page-narrow prose-page">
            <PageHeader eyebrow="About" title="About CampusBridge" />

            <section aria-labelledby="problem">
                <h2 id="problem">The problem</h2>
                <p>
                    University students hear about scholarships, internships, events and clubs through many disconnected
                    channels — email, group chats, posters and social media. Our working hypotheses are that this makes it
                    easy to miss deadlines, hard to discover activities outside your immediate circle, and slow to find what
                    is relevant to you. We are checking these hypotheses with a short student survey and interviews.
                </p>
            </section>

            <section aria-labelledby="approach">
                <h2 id="approach">Our approach</h2>
                <ul>
                    <li><strong>Unified discovery</strong> — one search across opportunities, events and organizations.</li>
                    <li><strong>Explainable recommendations</strong> — a transparent points system, not a black box.</li>
                    <li><strong>Deadline and schedule intelligence</strong> — urgency grouping, clash detection and calendar export.</li>
                    <li><strong>Role-specific workflows</strong> — organization reps submit events; administrators review them.</li>
                </ul>
            </section>

            <section id="recommendations" aria-labelledby="recommendations-title">
                <h2 id="recommendations-title">How recommendations work</h2>
                <p>
                    CampusBridge uses <strong>simple rules, not machine learning</strong>. Every published opportunity and
                    upcoming event you have not saved yet earns points from the rules below. Items must match at least one of
                    your interests or come from an organization you follow. The highest scores are shown first, and each card
                    lists the rules that applied.
                </p>
                <table className="simple-table card">
                    <caption className="sr-only">Recommendation scoring rules</caption>
                    <thead><tr><th scope="col">Rule</th><th scope="col">Points</th></tr></thead>
                    <tbody>
                        {meta.recommendation_rules.map(([rule, points]) => <tr key={rule}><td>{rule}</td><td><strong>{points}</strong></td></tr>)}
                    </tbody>
                </table>
                <p>Ties go to the soonest date, then alphabetical order, so the result is always the same for the same data.</p>
            </section>

            <section id="privacy" aria-labelledby="privacy-title">
                <h2 id="privacy-title">Privacy &amp; data</h2>
                <ul>
                    <li>An account stores your name, email and a <strong>hashed</strong> password (we never store the password itself).</li>
                    <li>We also store the interests, saved items and follows you choose. You can delete them from Preferences.</li>
                    <li>Organization reps can only change their own organization’s events, and only administrators can publish listings. These rules are checked on the server for every request.</li>
                </ul>
            </section>

            <Notice title="Independent project disclaimer">
                CampusBridge is a student project for the CS341 Web Technologies course. It is not affiliated with, endorsed by or
                operated by any university. All organizations, opportunities, events and figures in the sample database are fictional;
                links point to the reserved example.org domain.
            </Notice>
        </div>
    );
}
