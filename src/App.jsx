import React, { useState } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import useAppStore from './store/appStore';
import Sidebar from './components/Sidebar';
import Header from './components/Header';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Customers from './pages/Customers';
import Vehicles from './pages/Vehicles';
import './index.css';

function App() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const { isAuthenticated, user, logout } = useAppStore();

  if (!isAuthenticated) {
    return <Login />;
  }

  return (
    <Router>
      <div className="flex h-screen bg-neutral-50">
        {/* Sidebar */}
        <Sidebar
          isOpen={sidebarOpen}
          toggleSidebar={() => setSidebarOpen(!sidebarOpen)}
          onLogout={logout}
        />

        {/* Main Content */}
        <div className="flex-1 flex flex-col overflow-hidden md:ml-0">
          <Header user={user} />

          {/* Page Content */}
          <main className="flex-1 overflow-y-auto p-6">
            <Routes>
              <Route path="/dashboard" element={<Dashboard />} />
              <Route path="/customers" element={<Customers />} />
              <Route path="/vehicles" element={<Vehicles />} />
              <Route path="/job-cards" element={<div className="text-center py-12">Job Cards Page - Coming Soon</div>} />
              <Route path="/invoices" element={<div className="text-center py-12">Invoices Page - Coming Soon</div>} />
              <Route path="/inventory" element={<div className="text-center py-12">Inventory Page - Coming Soon</div>} />
              <Route path="/accounts" element={<div className="text-center py-12">Accounts Page - Coming Soon</div>} />
              <Route path="/settings" element={<div className="text-center py-12">Settings Page - Coming Soon</div>} />
              <Route path="*" element={<Navigate to="/dashboard" />} />
            </Routes>
          </main>
        </div>
      </div>
    </Router>
  );
}

export default App;