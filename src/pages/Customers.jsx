import React, { useState } from 'react';
import { Plus, Trash2, Edit2, Phone, Mail, MapPin } from 'lucide-react';
import useAppStore from '../store/appStore';

const Customers = () => {
  const { customers, setCustomers } = useAppStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    mobile: '',
    email: '',
    address: '',
    whatsapp: '',
  });

  const filteredCustomers = customers.filter((customer) =>
    customer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    customer.mobile.includes(searchTerm) ||
    customer.id.includes(searchTerm)
  );

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.mobile.trim()) {
      alert('Name and Mobile are required');
      return;
    }

    if (editingId) {
      setCustomers(
        customers.map((c) =>
          c.id === editingId ? { ...c, ...formData } : c
        )
      );
    } else {
      const newCustomer = {
        id: `CUST-${String(customers.length + 1).padStart(3, '0')}`,
        createdAt: new Date().toISOString().split('T')[0],
        ...formData,
      };
      setCustomers([...customers, newCustomer]);
    }

    setFormData({ name: '', mobile: '', email: '', address: '', whatsapp: '' });
    setShowForm(false);
    setEditingId(null);
  };

  const handleEdit = (customer) => {
    setFormData(customer);
    setEditingId(customer.id);
    setShowForm(true);
  };

  const handleDelete = (id) => {
    if (window.confirm('Are you sure you want to delete this customer?')) {
      setCustomers(customers.filter((c) => c.id !== id));
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-neutral-900">Customers</h1>
          <p className="text-neutral-600 mt-1">Manage your workshop customers</p>
        </div>
        <button
          onClick={() => {
            setShowForm(true);
            setEditingId(null);
            setFormData({ name: '', mobile: '', email: '', address: '', whatsapp: '' });
          }}
          className="btn-primary flex items-center gap-2"
        >
          <Plus size={20} />
          Add Customer
        </button>
      </div>

      {/* Search */}
      <div>
        <input
          type="text"
          placeholder="Search by name, mobile or customer ID..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="input-field"
        />
      </div>

      {/* Customers Grid */}
      {filteredCustomers.length === 0 ? (
        <div className="card p-12 text-center">
          <div className="text-4xl mb-4">👥</div>
          <h3 className="text-xl font-bold text-neutral-900">No customers found</h3>
          <p className="text-neutral-600 mt-2">Add your first customer to get started</p>
          <button
            onClick={() => {
              setShowForm(true);
              setEditingId(null);
            }}
            className="btn-primary mt-4"
          >
            <Plus size={18} className="inline mr-2" />
            Add First Customer
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredCustomers.map((customer) => (
            <div key={customer.id} className="card p-6 hover:shadow-lg">
              <div className="flex items-start justify-between mb-4">
                <div>
                  <div className="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center text-primary-600 font-bold text-lg">
                    {customer.name.charAt(0)}
                  </div>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleEdit(customer)}
                    className="p-2 text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                  >
                    <Edit2 size={18} />
                  </button>
                  <button
                    onClick={() => handleDelete(customer.id)}
                    className="p-2 text-danger-600 hover:bg-danger-50 rounded-lg transition-colors"
                  >
                    <Trash2 size={18} />
                  </button>
                </div>
              </div>

              <h3 className="text-lg font-bold text-neutral-900">{customer.name}</h3>
              <p className="text-sm text-neutral-500 mb-4">{customer.id}</p>

              <div className="space-y-2 mb-4">
                <div className="flex items-center gap-2 text-neutral-600">
                  <Phone size={16} />
                  <span className="text-sm">{customer.mobile}</span>
                </div>
                {customer.whatsapp && (
                  <div className="flex items-center gap-2 text-neutral-600">
                    <Phone size={16} />
                    <span className="text-sm">WhatsApp: {customer.whatsapp}</span>
                  </div>
                )}
                {customer.email && (
                  <div className="flex items-center gap-2 text-neutral-600">
                    <Mail size={16} />
                    <span className="text-sm truncate">{customer.email}</span>
                  </div>
                )}
                {customer.address && (
                  <div className="flex items-start gap-2 text-neutral-600">
                    <MapPin size={16} className="mt-1" />
                    <span className="text-sm line-clamp-2">{customer.address}</span>
                  </div>
                )}
              </div>

              <div className="pt-4 border-t border-neutral-200 text-xs text-neutral-500">
                Added on {customer.createdAt}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal Form */}
      {showForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-8">
            <h2 className="text-2xl font-bold text-neutral-900 mb-2">
              {editingId ? 'Edit Customer' : 'Add New Customer'}
            </h2>
            <p className="text-neutral-600 mb-6">Fill in the customer details below</p>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="label-text">Full Name *</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="input-field"
                  placeholder="John Doe"
                  required
                />
              </div>

              <div>
                <label className="label-text">Mobile Number *</label>
                <input
                  type="tel"
                  value={formData.mobile}
                  onChange={(e) => setFormData({ ...formData, mobile: e.target.value })}
                  className="input-field"
                  placeholder="9876543210"
                  required
                />
              </div>

              <div>
                <label className="label-text">WhatsApp Number</label>
                <input
                  type="tel"
                  value={formData.whatsapp}
                  onChange={(e) => setFormData({ ...formData, whatsapp: e.target.value })}
                  className="input-field"
                  placeholder="9876543210"
                />
              </div>

              <div>
                <label className="label-text">Email Address</label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="input-field"
                  placeholder="john@example.com"
                />
              </div>

              <div>
                <label className="label-text">Address</label>
                <textarea
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  className="input-field resize-none"
                  placeholder="Street address..."
                  rows="3"
                />
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => {
                    setShowForm(false);
                    setEditingId(null);
                  }}
                  className="btn-secondary flex-1"
                >
                  Cancel
                </button>
                <button type="submit" className="btn-primary flex-1">
                  {editingId ? 'Update' : 'Save'} Customer
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Customers;