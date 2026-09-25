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
            <Route index element={<Navigate to="extracurriculars" replace />} />
          </Route>

          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
