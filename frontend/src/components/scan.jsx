/*
 * The automatic privacy & copyright check of an uploaded resource, as shown to
 * the people who approve it (org reps on the Manage page, admins on the Admin page).
 */

/**
 * The red "Potential rule breach" label. The server sets scan.breach_warning when the
 * check failed, found serious problems, or has not finished: publishing then needs
 * the reviewer to confirm they checked the file themselves.
 */
export function BreachWarning({ scan }) {
    if (!scan.breach_warning) {
        return null;
    }
    return (
        <div className="breach-warning" role="note">
            <p className="breach-title"><span aria-hidden="true">⚠</span> Potential rule breach</p>
            <p>{scan.breach_warning}</p>
        </div>
    );
}

/** The scanner's findings, one line each. */
export function ScanResult({ scan }) {
    return (
        <div className="scan-result" data-testid="scan-result">
            {scan.state === 'pending' && <p className="flag flag-pending">⏳ Automatic privacy &amp; copyright check in progress…</p>}
            {scan.state === 'clean' && <p className="flag flag-ok">✓ Automatic check found no privacy or copyright issues</p>}
            {(scan.state === 'flagged' || scan.state === 'failed') && (
                <>
                    <p className="scan-heading">
                        {scan.state === 'failed'
                            ? 'Automatic check failed'
                            : `Automatic check: ${scan.flags.length} ${scan.flags.length === 1 ? 'thing' : 'things'} to look at`}
                    </p>
                    <ul className="plain-list" aria-label="Automatic privacy and copyright check">
                        {scan.flags.map((flag) => (
                            <li className={`flag flag-${flag.severity}`} key={flag.message}>
                                {flag.severity === 'critical' ? <>⛔ <span className="sr-only">Critical:</span></> : <>⚠ <span className="sr-only">Warning:</span></>}
                                <span className="flag-kind">{flag.kind_label}</span> {flag.message}
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
}
