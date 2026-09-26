import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './features/auth/hooks/useAuth';
import { LoginPage } from './features/auth/components/LoginPage';
import { AdminLayout } from './features/admin/components/AdminLayout';
import { RequireAuth, RequireAdmin } from './features/admin/components/AdminGuard';
import { AcademicYearsPage } from './features/admin/pages/AcademicYearsPage';
import { StudentsPage } from './features/admin/pages/StudentsPage';
import { AdminRegistrationsPage } from './features/admin/pages/RegistrationsPage';
import { AdminPaymentsPage } from './features/payments/pages/AdminPaymentsPage';
import { AdminPaymentDetailPage } from './features/payments/pages/AdminPaymentDetailPage';
import { MyPaymentsPage } from './features/payments/pages/MyPaymentsPage';
import { MyPaymentDetailPage } from './features/payments/pages/MyPaymentDetailPage';
import { StudentLayout, RequireStudent } from './features/registrations/components/StudentLayout';
import { ExtracurricularsPage } from './features/registrations/pages/ExtracurricularsPage';
import { ExtracurricularDetailPage } from './features/registrations/pages/ExtracurricularDetailPage';
import { MyRegistrationsPage } from './features/registrations/pages/MyRegistrationsPage';
import { MyRegistrationDetailPage } from './features/registrations/pages/MyRegistrationDetailPage';
import { AdminSessionsPage } from './features/admin/pages/AdminSessionsPage';
import { AdminSessionDetailPage } from './features/admin/pages/AdminSessionDetailPage';
import { CoachLayout, RequireCoach } from './features/attendance/components/CoachLayout';
import { CoachSessionsPage } from './features/attendance/pages/CoachSessionsPage';
import { CoachSessionDetailPage } from './features/attendance/pages/CoachSessionDetailPage';
import { CoachAttendancePage } from './features/attendance/pages/CoachAttendancePage';
import { StudentSessionsPage } from './features/attendance/pages/StudentSessionsPage';
import { StudentSessionDetailPage } from './features/attendance/pages/StudentSessionDetailPage';
import { StudentAttendancePage } from './features/attendance/pages/StudentAttendancePage';
import { StudentAttendanceDetailPage } from './features/attendance/pages/StudentAttendanceDetailPage';
import './App.css';

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route
            path="/admin"
            element={
              <RequireAuth>
                <RequireAdmin>
                  <AdminLayout />
                </RequireAdmin>
              </RequireAuth>
            }
          >
            <Route path="academic-years" element={<AcademicYearsPage />} />
            <Route path="students" element={<StudentsPage />} />
            <Route path="registrations" element={<AdminRegistrationsPage />} />
            <Route path="payments" element={<AdminPaymentsPage />} />
            <Route path="payments/:id" element={<AdminPaymentDetailPage />} />
            <Route path="sessions" element={<AdminSessionsPage />} />
            <Route path="sessions/:id" element={<AdminSessionDetailPage />} />
            <Route index element={<Navigate to="registrations" replace />} />
          </Route>

          <Route
            path="/student"
            element={
              <RequireAuth>
                <RequireStudent>
                  <StudentLayout />
                </RequireStudent>
              </RequireAuth>
            }
          >
            <Route path="extracurriculars" element={<ExtracurricularsPage />} />
            <Route path="extracurriculars/:id" element={<ExtracurricularDetailPage />} />
            <Route path="registrations" element={<MyRegistrationsPage />} />
            <Route path="registrations/:id" element={<MyRegistrationDetailPage />} />
            <Route path="payments" element={<MyPaymentsPage />} />
            <Route path="payments/:id" element={<MyPaymentDetailPage />} />
            <Route path="sessions" element={<StudentSessionsPage />} />
            <Route path="sessions/:id" element={<StudentSessionDetailPage />} />
            <Route path="attendance" element={<StudentAttendancePage />} />
            <Route path="attendance/:id" element={<StudentAttendanceDetailPage />} />
            <Route index element={<Navigate to="extracurriculars" replace />} />
          </Route>

          <Route
            path="/coach"
            element={
              <RequireAuth>
                <RequireCoach>
                  <CoachLayout />
                </RequireCoach>
              </RequireAuth>
            }
          >
            <Route path="sessions" element={<CoachSessionsPage />} />
            <Route path="sessions/:id" element={<CoachSessionDetailPage />} />
            <Route path="attendance" element={<CoachAttendancePage />} />
            <Route index element={<Navigate to="sessions" replace />} />
          </Route>

          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
