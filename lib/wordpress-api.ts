// WordPress REST API client configuration
import { API_CONFIG } from "./config"

export interface WordPressPost {
  id: number
  title: { rendered: string }
  content: { rendered: string }
  excerpt: { rendered: string }
  slug: string
  date: string
  modified: string
  status: string
  meta: Record<string, any>
  acf?: Record<string, any> // Advanced Custom Fields
}

export interface TrainData {
  id: string
  name: string
  number: string
  route: string
  departure_time?: string
  arrival_time?: string
  duration?: string
  from_station?: string
  to_station?: string
}

export interface SeatData {
  id: string
  seat_number: string
  coach_type: string
  is_available: boolean
  base_fare: number
}

export interface BookingData {
  id: string
  train_run_id: string
  seat_id: string
  passenger_name: string
  passenger_email: string
  passenger_phone: string
  booking_reference: string
  booking_status: string
  fare_amount: number
}

class WordPressAPI {
  private baseUrl: string

  constructor() {
    this.baseUrl = API_CONFIG.RAIL_BASE_URL
  }

  // Generic fetch method for WordPress API
  private async fetchFromWordPress(endpoint: string, options: RequestInit = {}): Promise<any> {
    const url = `${this.baseUrl}${endpoint}`

    const response = await fetch(url, {
      headers: {
        ...API_CONFIG.DEFAULT_HEADERS,
        ...options.headers,
      },
      ...options,
    })

    if (!response.ok) {
      throw new Error(`WordPress API error: ${response.status} ${response.statusText}`)
    }

    return response.json()
  }

  // Search trains by route or query
  async searchTrains(params: { from?: string; to?: string; query?: string }): Promise<TrainData[]> {
    let endpoint = "/posts?post_type=train&per_page=10"

    if (params.query) {
      endpoint += `&search=${encodeURIComponent(params.query)}`
    }

    if (params.from || params.to) {
      // Use meta query for route-based search
      const metaQuery = []
      if (params.from) metaQuery.push(`from_station=${encodeURIComponent(params.from)}`)
      if (params.to) metaQuery.push(`to_station=${encodeURIComponent(params.to)}`)
      endpoint += `&${metaQuery.join("&")}`
    }

    try {
      const posts = await this.fetchFromWordPress(endpoint)

      return posts.map((post: WordPressPost) => ({
        id: post.id.toString(),
        name: post.title.rendered,
        number: post.meta?.train_number || post.acf?.train_number || "",
        route: post.meta?.route || post.acf?.route || "",
        departure_time: post.meta?.departure_time || post.acf?.departure_time,
        arrival_time: post.meta?.arrival_time || post.acf?.arrival_time,
        duration: post.meta?.duration || post.acf?.duration,
        from_station: post.meta?.from_station || post.acf?.from_station,
        to_station: post.meta?.to_station || post.acf?.to_station,
      }))
    } catch (error) {
      console.error("Error searching trains:", error)
      return []
    }
  }

  // Get train seats
  async getTrainSeats(trainRunId: string): Promise<SeatData[]> {
    const endpoint = `/posts?post_type=seat&meta_key=train_run_id&meta_value=${trainRunId}&per_page=100`

    try {
      const posts = await this.fetchFromWordPress(endpoint)

      return posts.map((post: WordPressPost) => ({
        id: post.id.toString(),
        seat_number: post.title.rendered,
        coach_type: post.meta?.coach_type || post.acf?.coach_type || "",
        is_available: post.meta?.is_available !== "false" && post.acf?.is_available !== false,
        base_fare: Number.parseFloat(post.meta?.base_fare || post.acf?.base_fare || "0"),
      }))
    } catch (error) {
      console.error("Error fetching seats:", error)
      return []
    }
  }

  // Create booking
  async createBooking(
    bookingData: Omit<BookingData, "id">,
  ): Promise<{ success: boolean; id?: string; error?: string }> {
    const endpoint = "/posts"

    try {
      const response = await this.fetchFromWordPress(endpoint, {
        method: "POST",
        body: JSON.stringify({
          title: `Booking ${bookingData.booking_reference}`,
          content: `Booking for ${bookingData.passenger_name}`,
          status: "publish",
          post_type: "booking",
          meta: {
            train_run_id: bookingData.train_run_id,
            seat_id: bookingData.seat_id,
            passenger_name: bookingData.passenger_name,
            passenger_email: bookingData.passenger_email,
            passenger_phone: bookingData.passenger_phone,
            booking_reference: bookingData.booking_reference,
            booking_status: bookingData.booking_status,
            fare_amount: bookingData.fare_amount.toString(),
          },
        }),
      })

      return { success: true, id: response.id.toString() }
    } catch (error) {
      console.error("Error creating booking:", error)
      return { success: false, error: "Failed to create booking" }
    }
  }

  // Get bookings
  async getBookings(filters?: { booking_reference?: string; passenger_email?: string }): Promise<BookingData[]> {
    let endpoint = "/posts?post_type=booking&per_page=100"

    if (filters?.booking_reference) {
      endpoint += `&meta_key=booking_reference&meta_value=${encodeURIComponent(filters.booking_reference)}`
    }

    if (filters?.passenger_email) {
      endpoint += `&meta_key=passenger_email&meta_value=${encodeURIComponent(filters.passenger_email)}`
    }

    try {
      const posts = await this.fetchFromWordPress(endpoint)

      return posts.map((post: WordPressPost) => ({
        id: post.id.toString(),
        train_run_id: post.meta?.train_run_id || post.acf?.train_run_id || "",
        seat_id: post.meta?.seat_id || post.acf?.seat_id || "",
        passenger_name: post.meta?.passenger_name || post.acf?.passenger_name || "",
        passenger_email: post.meta?.passenger_email || post.acf?.passenger_email || "",
        passenger_phone: post.meta?.passenger_phone || post.acf?.passenger_phone || "",
        booking_reference: post.meta?.booking_reference || post.acf?.booking_reference || "",
        booking_status: post.meta?.booking_status || post.acf?.booking_status || "pending",
        fare_amount: Number.parseFloat(post.meta?.fare_amount || post.acf?.fare_amount || "0"),
      }))
    } catch (error) {
      console.error("Error fetching bookings:", error)
      return []
    }
  }
}

export const wordpressAPI = new WordPressAPI()
