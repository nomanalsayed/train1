import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const from = searchParams.get("from")
    const to = searchParams.get("to")
    const query = searchParams.get("query")

    const apiUrl = `${API_CONFIG.RAIL_BASE_URL}/trains?per_page=100`

    const response = await fetch(apiUrl, {
      headers: API_CONFIG.DEFAULT_HEADERS,
    })

    if (!response.ok) {
      throw new Error(`API responded with status: ${response.status}`)
    }

    const data = await response.json()
    let trains = data.items || []

    // Filter trains based on search criteria
    if (query) {
      trains = trains.filter(
        (train: any) =>
          train.train_name.toLowerCase().includes(query.toLowerCase()) ||
          train.code_from_to.includes(query) ||
          train.code_to_from.includes(query),
      )
    }

    if (from && to) {
      trains = trains.filter(
        (train: any) =>
          (train.from_station.title.toLowerCase().includes(from.toLowerCase()) &&
            train.to_station.title.toLowerCase().includes(to.toLowerCase())) ||
          (train.from_station.title.toLowerCase().includes(to.toLowerCase()) &&
            train.to_station.title.toLowerCase().includes(from.toLowerCase())),
      )
    }

    const formattedTrains = trains.map((train: any) => ({
      id: train.id,
      name: train.train_name,
      number: train.code_from_to,
      route: `${train.from_station.title} - ${train.to_station.title}`,
    }))

    return NextResponse.json({ trains: formattedTrains })
  } catch (error) {
    console.error("Direction search error:", error)
    return NextResponse.json({ error: "Failed to search trains" }, { status: 500 })
  }
}
