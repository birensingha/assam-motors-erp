import React, { useState } from 'react';
import { Plus, Trash2, Edit2, Car } from 'lucide-react';
import useAppStore from '../store/appStore';

const Vehicles = () => {
  const { vehicles, setVehicles, customers } = useAppStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [formData, setFormData] = useState({
    vehicleNumber: '',
    customerId: '',
    brand: '',
    model: '',
    variant: '',
    fuelType: 'Petrol',
    year: '',
    km: '',
    chassisNumber: '',
    engineNumber: '',
  });

  const filteredVehicles = vehicles.filter((v) =>
    v.vehicleNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
    v.brand.toLowerCase().includes(searchTerm.toLowerCase()) ||
    v.model.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const getCustomerName = (customerId) => {
    return customers.find((c) => c.id === customerId)?.name || 'Unknown';
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.vehicleNumber.trim() || !formData.customerId) {
      alert('Vehicle Number and Customer are required');
      return;
    }

    if (editingId) {
      setVehicles(
        vehicles.map((v) =>
          v.id === editingId ? { ...v, ...formData } : v
        )
      );
    } else {
      const newVehicle = {
        id: `VEH-${String(vehicles.length + 1).padStart(3, '0')}`,
        createdAt: new Date().toISOString().split('T')[0],
        ...formData,
      };
      setVehicles([...vehicles, newVehicle]);
    }

    setFormData({
      vehicleNumber: '',
      customerId: '',
      brand: '',
      model: '',
      variant: '',
      fuelType: 'Petrol',
      year: '',
      km: '',
      chassisNumber: '',
      engineNumber: '',
    });
    setShowForm(false);
    setEditingId(null);
  };

  const handleEdit = (vehicle) => {
    setFormData(vehicle);
    setEditingId(vehicle.id);
    setShowForm(true);
  };

  const handleDelete = (id) => {
    if (window.confirm('Are you sure you want to delete this vehicle?')) {
      setVehicles(vehicles.filter((v) => v.id !== id));
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-neutral-900">Vehicles</h1>
          <p className="text-neutral-600 mt-1">Manage customer vehicles</p>
        </div>
        <button
          onClick={() => {
            setShowForm(true);
            setEditingId(null);
            setFormData({
              vehicleNumber: '',
              customerId: '',
              brand: '',
              model: '',
              variant: '',
              fuelType: 'Petrol',
              year: '',
              km: '',
              chassisNumber: '',
              engineNumber: '',
            });
          }}
          className="btn-primary flex items-center gap-2"
        >
          <Plus size={20} />
          Add Vehicle
        </button>
      </div>

      {/* Search */}
      <div>
        <input
          type="text"
          placeholder="Search by vehicle number, brand or model..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="input-field"
        />
      </div>

      {/* Vehicles Table */}
      {filteredVehicles.length === 0 ? (
        <div className="card p-12 text-center">
          <div className="text-4xl mb-4">🚗</div>
          <h3 className="text-xl font-bold text-neutral-900">No vehicles found</h3>
          <p className="text-neutral-600 mt-2">Add your first vehicle to get started</p>
        </div>
      ) : (
        <div className="card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-neutral-50 border-b border-neutral-200">
                <tr>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">Vehicle</th>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">Customer</th>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">Brand</th>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">Model</th>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">Year</th>
                  <th className="px-6 py-3 text-left text-sm font-semibold text-neutral-900">KM</th>
                  <th className="px-6 py-3 text-right text-sm font-semibold text-neutral-900">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200">
                {filteredVehicles.map((vehicle) => (
                  <tr key={vehicle.id} className="hover:bg-neutral-50">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center text-primary-600">
                          <Car size={18} />
                        </div>
                        <div>
                          <p className="font-semibold text-neutral-900">{vehicle.vehicleNumber}</p>
                          <p className="text-xs text-neutral-500">{vehicle.id}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-sm text-neutral-900">{getCustomerName(vehicle.customerId)}</td>
                    <td className="px-6 py-4 text-sm text-neutral-900">{vehicle.brand}</td>
                    <td className="px-6 py-4 text-sm text-neutral-900">{vehicle.model}</td>
                    <td className="px-6 py-4 text-sm text-neutral-900">{vehicle.year || '-'}</td>
                    <td className="px-6 py-4 text-sm text-neutral-900">{vehicle.km || '-'}</td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex justify-end gap-2">
                        <button
                          onClick={() => handleEdit(vehicle)}
                          className="p-2 text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                        >
                          <Edit2 size={18} />
                        </button>
                        <button
                          onClick={() => handleDelete(vehicle.id)}
                          className="p-2 text-danger-600 hover:bg-danger-50 rounded-lg transition-colors"
                        >
                          <Trash2 size={18} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Modal Form */}
      {showForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-8 max-h-[90vh] overflow-y-auto">
            <h2 className="text-2xl font-bold text-neutral-900 mb-2">
              {editingId ? 'Edit Vehicle' : 'Add New Vehicle'}
            </h2>
            <p className="text-neutral-600 mb-6">Fill in the vehicle details below</p>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="label-text">Vehicle Number *</label>
                  <input
                    type="text"
                    value={formData.vehicleNumber}
                    onChange={(e) =>
                      setFormData({ ...formData, vehicleNumber: e.target.value.toUpperCase() })
                    }
                    className="input-field"
                    placeholder="AS01AB1234"
                    required
                  />
                </div>

                <div>
                  <label className="label-text">Customer *</label>
                  <select
                    value={formData.customerId}
                    onChange={(e) => setFormData({ ...formData, customerId: e.target.value })}
                    className="input-field"
                    required
                  >
                    <option value="">Select Customer</option>
                    {customers.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="label-text">Brand</label>
                  <input
                    type="text"
                    value={formData.brand}
                    onChange={(e) => setFormData({ ...formData, brand: e.target.value })}
                    className="input-field"
                    placeholder="Maruti"
                  />
                </div>

                <div>
                  <label className="label-text">Model</label>
                  <input
                    type="text"
                    value={formData.model}
                    onChange={(e) => setFormData({ ...formData, model: e.target.value })}
                    className="input-field"
                    placeholder="Swift"
                  />
                </div>

                <div>
                  <label className="label-text">Variant</label>
                  <input
                    type="text"
                    value={formData.variant}
                    onChange={(e) => setFormData({ ...formData, variant: e.target.value })}
                    className="input-field"
                    placeholder="VXI"
                  />
                </div>

                <div>
                  <label className="label-text">Fuel Type</label>
                  <select
                    value={formData.fuelType}
                    onChange={(e) => setFormData({ ...formData, fuelType: e.target.value })}
                    className="input-field"
                  >
                    <option>Petrol</option>
                    <option>Diesel</option>
                    <option>CNG</option>
                    <option>Hybrid</option>
                    <option>Electric</option>
                  </select>
                </div>

                <div>
                  <label className="label-text">Manufacturing Year</label>
                  <input
                    type="number"
                    value={formData.year}
                    onChange={(e) => setFormData({ ...formData, year: e.target.value })}
                    className="input-field"
                    placeholder="2024"
                  />
                </div>

                <div>
                  <label className="label-text">KM Reading</label>
                  <input
                    type="number"
                    value={formData.km}
                    onChange={(e) => setFormData({ ...formData, km: e.target.value })}
                    className="input-field"
                    placeholder="25000"
                  />
                </div>

                <div className="md:col-span-2">
                  <label className="label-text">Chassis Number</label>
                  <input
                    type="text"
                    value={formData.chassisNumber}
                    onChange={(e) => setFormData({ ...formData, chassisNumber: e.target.value })}
                    className="input-field"
                    placeholder="Chassis number"
                  />
                </div>

                <div className="md:col-span-2">
                  <label className="label-text">Engine Number</label>
                  <input
                    type="text"
                    value={formData.engineNumber}
                    onChange={(e) => setFormData({ ...formData, engineNumber: e.target.value })}
                    className="input-field"
                    placeholder="Engine number"
                  />
                </div>
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
                  {editingId ? 'Update' : 'Save'} Vehicle
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default Vehicles;