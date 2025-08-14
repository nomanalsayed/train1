import { TrainDirectionSearch } from "@/components/train-direction-search"

export default function Home() {
  return (
    <main className="min-h-screen bg-gray-50">
      {/* Header Section */}
      <div className="bg-white border-b border-gray-200">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 bg-emerald-600 rounded flex items-center justify-center">
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
          {/* Search Component */}
          <TrainDirectionSearch />
        </div>
      </div>
    </main>
  )
}