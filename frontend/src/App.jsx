/*
 * Which page to show for which address (React Router).
 * Every page gets its data from the PHP API; see src/pages/.
 */
import { Route, Routes } from 'react-router-dom';
import { Layout } from './components/Layout.jsx';
import { EmptyState, Loading, NotFound } from './components/ui.jsx';
import { useSession } from './session.jsx';
import { About } from './pages/About.jsx';
import { Admin } from './pages/Admin.jsx';
import { Approvals } from './pages/Approvals.jsx';
import { EventPage, OpportunityPage, OrganizationPage } from './pages/Details.jsx';
import { Explore } from './pages/Explore.jsx';
import { Home } from './pages/Home.jsx';
import { Events, Organizations } from './pages/Lists.jsx';
import { Login, Register } from './pages/Account.jsx';
import { Manage, ManageEvent } from './pages/Manage.jsx';
import { ResourceUpload, Resources } from './pages/Resources.jsx';
import { Calendar, Dashboard, Preferences, Saved } from './pages/Student.jsx';

export function App() {
    const { meta, loadError } = useSession();

    if (loadError) {
        return (
            <div className="container page-narrow">
                <EmptyState title="CampusBridge is not available" description={loadError.message} />
            </div>
        );
    }
    if (!meta) {
        return <Loading />;
    }

    return (
        <Routes>
            <Route element={<Layout />}>
                <Route index element={<Home />} />
                <Route path="explore" element={<Explore />} />
                <Route path="opportunities/:id" element={<OpportunityPage />} />
                <Route path="events" element={<Events />} />
                <Route path="events/:id" element={<EventPage />} />
                <Route path="organizations" element={<Organizations />} />
                <Route path="organizations/:id" element={<OrganizationPage />} />
                <Route path="resources" element={<Resources />} />
                <Route path="resources/new" element={<ResourceUpload />} />
                <Route path="login" element={<Login />} />
                <Route path="register" element={<Register />} />
                <Route path="dashboard" element={<Dashboard />} />
                <Route path="saved" element={<Saved />} />
                <Route path="calendar" element={<Calendar />} />
                <Route path="preferences" element={<Preferences />} />
                <Route path="manage" element={<Manage />} />
                <Route path="manage/events/new" element={<ManageEvent />} />
                <Route path="manage/events/:id" element={<ManageEvent />} />
                <Route path="admin" element={<Admin />} />
                <Route path="approvals" element={<Approvals />} />
                <Route path="about" element={<About />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}
