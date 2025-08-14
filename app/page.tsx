import { TrainDirectionSearch } from "@/components/train-direction-search"

export default function Home() {
  return (
    <main className="min-h-screen bg-gradient-to-br from-emerald-50 via-blue-50 to-cyan-50">
      {/* Header Section */}
      <div className="bg-white/80 backdrop-blur-sm border-b border-emerald-100 sticky top-0 z-10">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center">
              <span className="text-white font-bold text-xl">🚂</span>
            </div>
            <div>
              <h1 className="text-xl font-bold text-gray-900">Railway Seat Guide</h1>
              <p className="text-sm text-gray-600">Find your perfect seat</p>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto">
          {/* Welcome Card */}
          <div className="bg-white rounded-2xl shadow-lg border border-emerald-100 p-6 mb-8">
            <div className="text-center mb-6">
              <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-2xl">🎯</span>
              </div>
              <h2 className="text-2xl font-bold text-gray-900 mb-2">
                Smart Seat Selection
              </h2>
              <p className="text-gray-600 leading-relaxed">
                Never sit backwards again! Our intelligent system shows you which seats face forward based on your travel direction.
              </p>
            </div>
          </div>

          {/* Search Component */}
          <TrainDirectionSearch />

          {/* Help Section */}
          <div className="bg-white rounded-2xl shadow-lg border border-blue-100 p-6 mt-8">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <span className="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                <span className="text-sm">💡</span>
              </span>
              How it works
            </h3>
            <div className="space-y-3 text-sm text-gray-600">
              <div className="flex items-start space-x-3">
                <span className="w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-semibold mt-0.5">1</span>
                <p>Search for your train by route or train number</p>
              </div>
              <div className="flex items-start space-x-3">
                <span className="w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-semibold mt-0.5">2</span>
                <p>Select your coach and view the seat layout</p>
              </div>
              <div className="flex items-start space-x-3">
                <span className="w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-semibold mt-0.5">3</span>
                <p>Green seats face forward, red seats face backward</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}