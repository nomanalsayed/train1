export default function Loading() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50 flex items-center justify-center px-4">
      <div className="text-center bg-white rounded-2xl p-8 shadow-lg border border-emerald-100 max-w-sm w-full">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mx-auto mb-4"></div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Loading Train Information</h3>
        <p className="text-gray-600 text-sm">Fetching seat directions...</p>
        <div className="mt-4 bg-gray-100 rounded-full h-2 overflow-hidden">
          <div className="animate-pulse bg-emerald-500 h-full w-3/4 rounded-full"></div>
        </div>
      </div>
    </div>
  )
}
